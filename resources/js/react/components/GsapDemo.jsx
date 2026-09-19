import { useRef } from 'react';
import { gsap, useGSAP } from '@/gsap';

/**
 * Minimal proof that React + @gsap/react (useGSAP) is wired up correctly.
 *
 * `useGSAP` scopes every animation created inside it to `container`, and
 * automatically reverts them when the component unmounts — no manual cleanup.
 */
export default function GsapDemo({ heading = 'GSAP + React is live' }) {
    const container = useRef(null);

    useGSAP(
        () => {
            gsap.from('[data-anim="word"]', {
                yPercent: 120,
                opacity: 0,
                duration: 0.8,
                ease: 'power3.out',
                stagger: 0.08,
            });
        },
        { scope: container },
    );

    return (
        <div ref={container} className="inline-flex flex-wrap gap-2 overflow-hidden">
            {heading.split(' ').map((word, i) => (
                <span key={i} data-anim="word" className="inline-block">
                    {word}
                </span>
            ))}
        </div>
    );
}
