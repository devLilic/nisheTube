import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <rect width="40" height="40" rx="12" fill="currentColor" />
            <path
                d="M16 12.2a1.4 1.4 0 0 1 2.1-1.2l10.2 7.8a1.5 1.5 0 0 1 0 2.4L18.1 29A1.4 1.4 0 0 1 16 27.8V12.2Z"
                fill="white"
            />
            <circle cx="10" cy="12" r="2" fill="white" opacity=".8" />
            <circle cx="10" cy="20" r="2" fill="white" opacity=".55" />
            <circle cx="10" cy="28" r="2" fill="white" opacity=".3" />
        </svg>
    );
}
