/**
 * Central GSAP setup.
 *
 * Every plugin below ships inside the public `gsap` package (3.13+ made the
 * former Club plugins free), so nothing extra needs to be installed.
 *
 * Import this module once and pull `gsap` (and the plugins the app actually
 * animates with) from here instead of from `gsap` directly — that guarantees the
 * plugins are registered before use:
 *
 *   import { gsap, ScrollTrigger, SplitText } from '@/gsap';
 *
 * Only the plugins in use are registered: every extra import is bundle weight
 * the browser has to parse before the first paint. Need another one? Import it
 * from `gsap/...` in the component that uses it and add it here.
 */
import { gsap } from 'gsap';
import { useGSAP } from '@gsap/react';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ScrollToPlugin } from 'gsap/ScrollToPlugin';
import { SplitText } from 'gsap/SplitText';

gsap.registerPlugin(useGSAP, ScrollTrigger, ScrollToPlugin, SplitText);

export { gsap, useGSAP, ScrollTrigger, ScrollToPlugin, SplitText };
