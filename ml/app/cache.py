"""A small bounded cache for point-sampled upstream APIs.

Soil properties and climate normals change on the scale of years, but the
analysis pipeline asks for them once per drone survey — and a farmer who
re-analyses the same field pays for the same SoilGrids round trip (which takes
seconds) every time. Caching those samples by rounded coordinate keeps the
public APIs from being hammered and keeps repeat analyses fast.

The bound matters: this process is long-lived, so an unbounded dict would grow
with every distinct coordinate ever sampled.
"""

from __future__ import annotations

from collections import OrderedDict

# Coordinates are matched at ~1 m precision, far finer than either dataset's
# native resolution (250 m for SoilGrids, ~50 km for NASA POWER climatology).
COORDINATE_PRECISION = 5


class BoundedCache[T]:
    """A FIFO cache keyed by rounded (latitude, longitude) pairs."""

    def __init__(self, maxsize: int = 256) -> None:
        if maxsize < 1:
            raise ValueError("A cache must hold at least one entry.")

        self.maxsize = maxsize
        self._entries: OrderedDict[tuple[float, float], T] = OrderedDict()

    @staticmethod
    def key(latitude: float, longitude: float) -> tuple[float, float]:
        return (round(latitude, COORDINATE_PRECISION), round(longitude, COORDINATE_PRECISION))

    def get(self, latitude: float, longitude: float) -> T | None:
        return self._entries.get(self.key(latitude, longitude))

    def put(self, latitude: float, longitude: float, value: T) -> T:
        self._entries[self.key(latitude, longitude)] = value

        while len(self._entries) > self.maxsize:
            self._entries.popitem(last=False)

        return value

    def __len__(self) -> int:
        return len(self._entries)
