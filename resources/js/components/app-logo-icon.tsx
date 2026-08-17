import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
            data-brand-mark="nishetube"
        >
            <defs>
                <linearGradient
                    id="nishetube-mark-gradient"
                    x1="7"
                    y1="5"
                    x2="42"
                    y2="44"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stopColor="#635BFF" />
                    <stop offset="0.55" stopColor="#7C3AED" />
                    <stop offset="1" stopColor="#4338CA" />
                </linearGradient>
                <linearGradient
                    id="nishetube-mark-shine"
                    x1="11"
                    y1="8"
                    x2="36"
                    y2="39"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stopColor="white" stopOpacity="0.24" />
                    <stop offset="1" stopColor="white" stopOpacity="0" />
                </linearGradient>
            </defs>
            <rect
                x="2"
                y="2"
                width="44"
                height="44"
                rx="14"
                fill="url(#nishetube-mark-gradient)"
            />
            <path
                d="M3.5 20.5C7.8 9.5 18.6 3.2 30.1 4.1c5.2.4 9.6 2.4 13.1 5.4A14 14 0 0 1 46 18.1V16A14 14 0 0 0 32 2H16A14 14 0 0 0 2 16v16a14 14 0 0 0 .8 4.7c-1.2-5.2-1-10.7.7-16.2Z"
                fill="url(#nishetube-mark-shine)"
            />
            <circle
                cx="21.5"
                cy="21.5"
                r="11"
                fill="white"
                fillOpacity="0.12"
                stroke="white"
                strokeWidth="2.5"
            />
            <path
                d="m18.6 16.7 7.8 4.8-7.8 4.8v-9.6Z"
                fill="white"
                stroke="white"
                strokeLinejoin="round"
                strokeWidth="1.2"
            />
            <path
                d="m29.5 29.5 7.8 7.8"
                stroke="white"
                strokeLinecap="round"
                strokeWidth="3.5"
            />
            <path
                d="m33.5 11.8 1.2-3.1 1.2 3.1 3.1 1.2-3.1 1.2-1.2 3.1-1.2-3.1-3.1-1.2 3.1-1.2Z"
                fill="#A5F3FC"
            />
        </svg>
    );
}
