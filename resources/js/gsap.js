/**
 * Central GSAP setup.
 *
 * Every plugin below ships inside the public `gsap` package (3.13+ made the
 * former Club plugins free), so nothing extra needs to be installed.
 *
 * Import this module once, then pull `gsap` (and any plugin you need) from here
 * instead of from `gsap` directly — that guarantees the plugins are registered
 * before you use them:
 *
 *   // Vanilla / Blade
 *   import { gsap, ScrollTrigger } from '@/gsap';
 *
 *   // React component
 *   import { gsap, useGSAP } from '@/gsap';
 *
 * `useGSAP` comes from `@gsap/react` and only runs inside React components; it
 * is registered here so the whole plugin surface lives in one place.
 *
 * Bundle-size note: `GSDevTools` and `MotionPathHelper` are authoring tools.
 * They are registered for parity with the full plugin list — drop those two
 * imports if you never open them in production.
 */
import { gsap } from 'gsap';
import { useGSAP } from '@gsap/react';

import { CustomEase } from 'gsap/CustomEase';
// CustomBounce requires CustomEase
import { CustomBounce } from 'gsap/CustomBounce';
// CustomWiggle requires CustomEase
import { CustomWiggle } from 'gsap/CustomWiggle';
import { RoughEase, ExpoScaleEase, SlowMo } from 'gsap/EasePack';

import { Draggable } from 'gsap/Draggable';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
// EaselPlugin needs EaselJS on the page to do anything
import { EaselPlugin } from 'gsap/EaselPlugin';
import { Flip } from 'gsap/Flip';
import { GSDevTools } from 'gsap/GSDevTools';
import { InertiaPlugin } from 'gsap/InertiaPlugin';
import { MotionPathHelper } from 'gsap/MotionPathHelper';
import { MotionPathPlugin } from 'gsap/MotionPathPlugin';
import { MorphSVGPlugin } from 'gsap/MorphSVGPlugin';
import { Observer } from 'gsap/Observer';
import { Physics2DPlugin } from 'gsap/Physics2DPlugin';
import { PhysicsPropsPlugin } from 'gsap/PhysicsPropsPlugin';
// PixiPlugin needs PIXI on the page to do anything
import { PixiPlugin } from 'gsap/PixiPlugin';
import { ScrambleTextPlugin } from 'gsap/ScrambleTextPlugin';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
// ScrollSmoother requires ScrollTrigger
import { ScrollSmoother } from 'gsap/ScrollSmoother';
import { ScrollToPlugin } from 'gsap/ScrollToPlugin';
import { SplitText } from 'gsap/SplitText';
import { TextPlugin } from 'gsap/TextPlugin';

gsap.registerPlugin(
    useGSAP,
    Draggable,
    DrawSVGPlugin,
    EaselPlugin,
    Flip,
    GSDevTools,
    InertiaPlugin,
    MotionPathHelper,
    MotionPathPlugin,
    MorphSVGPlugin,
    Observer,
    Physics2DPlugin,
    PhysicsPropsPlugin,
    PixiPlugin,
    ScrambleTextPlugin,
    ScrollTrigger,
    ScrollSmoother,
    ScrollToPlugin,
    SplitText,
    TextPlugin,
    RoughEase,
    ExpoScaleEase,
    SlowMo,
    CustomEase,
    CustomBounce,
    CustomWiggle,
);

export {
    gsap,
    useGSAP,
    CustomEase,
    CustomBounce,
    CustomWiggle,
    RoughEase,
    ExpoScaleEase,
    SlowMo,
    Draggable,
    DrawSVGPlugin,
    EaselPlugin,
    Flip,
    GSDevTools,
    InertiaPlugin,
    MotionPathHelper,
    MotionPathPlugin,
    MorphSVGPlugin,
    Observer,
    Physics2DPlugin,
    PhysicsPropsPlugin,
    PixiPlugin,
    ScrambleTextPlugin,
    ScrollTrigger,
    ScrollSmoother,
    ScrollToPlugin,
    SplitText,
    TextPlugin,
};
