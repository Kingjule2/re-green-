"""The shared contract for sampling a point from an upstream data source.

Three quite different datasets feed the agriculture context of an analysis:
elevation from a DEM, soil properties from SoilGrids, and climate normals from
NASA POWER. They differ in protocol, resolution and derivation, but the analysis
pipeline must treat them identically: every source is asked for one coordinate,
answers with a value plus its provenance, or explains why it could not answer.

That uniformity lives here — the report shape (`SampleReport`), the provenance
shape (`SourceInfo`), the source interface (`Sampler`), the failure vocabulary
(`SamplingError` / `SourceUnavailable`), and the resolution loop that walks the
candidate sources, honours the fallback switch, and never raises into a request
path. A missing dataset degrades one block of the result and nothing else.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from collections.abc import Callable, Sequence
from dataclasses import dataclass


class SamplingError(RuntimeError):
    """Raised when a sample cannot be produced or derived."""


class SourceUnavailable(SamplingError):
    """Raised when a source cannot deliver a sample for a point."""


def describe(exc: Exception) -> str:
    """Readable cause for a failure, even when the exception carries no message.

    httpx deliberately leaves connect timeouts message-less, and an operator
    staring at "could not be reached: " learns nothing from it.
    """
    return str(exc) or type(exc).__name__


@dataclass(frozen=True)
class SourceInfo:
    """Provenance of a sampled value: what was read, from whom, and where."""

    id: str
    label: str
    dataset: str
    provider: str
    service_url: str
    portal_url: str


@dataclass(frozen=True)
class SampleReport[T]:
    """A sample, or the reason there is none.

    `reason` is always filled when `available` is false, and it always names the
    sources that were tried and what they said, so a caller can tell "not
    measured" from "measured as nothing".
    """

    available: bool
    reason: str | None = None
    sample: T | None = None


class Sampler[T](ABC):
    """Contract for a source that samples one point."""

    info: SourceInfo

    def covers(self, latitude: float, longitude: float) -> bool:
        """Whether this source publishes data for the point at all."""
        return True

    @abstractmethod
    def unavailable_reason(self) -> str | None:
        """Why this source cannot sample right now, or None when it is ready."""

    @abstractmethod
    async def sample(self, latitude: float, longitude: float) -> T:
        """Sample the point, or raise `SourceUnavailable`."""


async def resolve[T](
    samplers: Sequence[Sampler[T]],
    latitude: float,
    longitude: float,
    *,
    fallback: bool = True,
    empty_reason: str = "no source is configured for this point",
) -> SampleReport[T]:
    """Walk the candidate samplers and return the first sample, or every failure.

    Never raises: a source that is unconfigured, unreachable or out of coverage
    is reported as an unavailable sample together with the reason. An empty
    `samplers` sequence means the feature is switched off or uncovered, and
    `empty_reason` explains which.
    """
    if not samplers:
        return SampleReport(available=False, reason=empty_reason)

    failures: list[str] = []

    for index, sampler in enumerate(samplers):
        reason = sampler.unavailable_reason()
        if reason is not None:
            failures.append(f"{sampler.info.label}: {reason}")
            continue

        # Only the first candidate is attempted when fallback is switched off.
        if index > 0 and not fallback:
            break

        try:
            return SampleReport(available=True, sample=await sampler.sample(latitude, longitude))
        except SamplingError as exc:
            failures.append(f"{sampler.info.label}: {exc}")

    return SampleReport(
        available=False,
        reason="; ".join(failures) or "no source could sample this point",
    )


def no_source_reason(
    mode: str,
    point: str,
    *,
    context: str,
    env_var: str,
    noun: str,
    pinned_label: Callable[[str], str | None],
) -> str:
    """Explain an empty candidate list in terms of the mode that was asked for."""
    if mode == "none":
        return f"{context} is disabled ({env_var}=none)"

    if mode == "auto":
        return f"no configured {noun} covers {point}"

    return f"{pinned_label(mode) or mode} does not cover {point}"
