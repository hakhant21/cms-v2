@props(['caption' => null])

@php
    $russia = __('ui.earth_connection.russia');
    $myanmar = __('ui.earth_connection.myanmar');
    $text = $caption ?: __('ui.earth_connection.caption');
@endphp

<div class="earth-connection" role="img" aria-label="{{ $russia }} – {{ $myanmar }}" data-reveal="zoom">
    <div class="earth-connection__halo" aria-hidden="true"></div>
    <svg class="earth-connection__globe" viewBox="0 0 420 420" aria-hidden="true">
        <defs>
            <radialGradient id="earth-surface" cx="35%" cy="26%" r="75%">
                <stop offset="0" stop-color="#d9f99d" stop-opacity=".9" />
                <stop offset=".36" stop-color="#34d399" stop-opacity=".82" />
                <stop offset="1" stop-color="#065f46" />
            </radialGradient>
            <linearGradient id="earth-edge" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#bef264" />
                <stop offset=".5" stop-color="#34d399" />
                <stop offset="1" stop-color="#052e2b" />
            </linearGradient>
            <filter id="earth-glow" x="-40%" y="-40%" width="180%" height="180%">
                <feGaussianBlur stdDeviation="7" result="blur" />
                <feMerge>
                    <feMergeNode in="blur" />
                    <feMergeNode in="SourceGraphic" />
                </feMerge>
            </filter>
            <clipPath id="earth-clip">
                <circle cx="210" cy="210" r="154" />
            </clipPath>
        </defs>

        <circle class="earth-connection__shadow" cx="210" cy="222" r="155" />
        <circle class="earth-connection__surface" cx="210" cy="210" r="154" fill="url(#earth-surface)" />

        <g class="earth-connection__rotation" clip-path="url(#earth-clip)">
            <g class="earth-connection__grid">
                <ellipse cx="210" cy="210" rx="62" ry="154" />
                <ellipse cx="210" cy="210" rx="122" ry="154" />
                <ellipse cx="210" cy="210" rx="154" ry="58" />
                <ellipse cx="210" cy="210" rx="154" ry="108" />
                <path d="M56 210h308" />
            </g>

            {{-- Natural Earth 110m country outlines projected onto the globe. --}}
            <path
                class="earth-connection__continent earth-connection__russia"
                d="M122.1 152.8 L116.1 157.3 L113.1 162.6 L111.6 156.4 L108.9 150.2 L109.2 143.4 L114.5 140.5 L119.1 136.2 L119 129.8 L121.2 124.1 L126.2 119.6 L129.1 113.7 L135.4 111.5 L141.4 109.6 L146.9 105.1 L153.5 101.9 L159.4 100.5 L157.7 107.3 L152 105.1 L147.5 109.2 L155.3 110.4 L162.9 112.1 L170 114.4 L175.8 119.5 L178.5 113.8 L184.2 110.4 L182.5 116.2 L179.3 121.7 L184 116.2 L190 114.1 L196.7 112 L202.1 108.9 L208.2 106.4 L215.3 108.3 L216.6 114.2 L224.1 114 L230.8 116.5 L236.5 114.3 L243 109.1 L249.4 108.4 L255.9 107.5 L260 101.6 L272.9 105.4 L278.4 108.9 L278.9 116.4 L278 122.3 L278.8 128.8 L283.3 133.9 L284.6 140.9 L277.4 138.2 L274.4 132.3 L273.8 124.3 L271.1 118.7 L272.1 125 L268.7 130.1 L264.4 134.5 L257.9 138 L254.6 151.1 L261.6 150.1 L266.1 156.6 L268.7 163.6 L268.3 169.8 L266.3 177.1 L261.4 180.7 L261.4 172.7 L261.1 166.2 L255.3 169.9 L248.3 166.4 L243.3 159.4 L237.2 158.9 L234.1 166.9 L228.1 169.7 L221.5 171.4 L215 170.6 L207.7 169.9 L201.6 165.7 L196.6 170.3 L189.8 167.7 L182.1 169.2 L176.4 166.5 L170.3 161.6 L168 155.6 L162 153.4 L158.7 146.8 L152.5 147 L146.6 146 L143.8 151.7 L137.8 148.9 L132.5 143 L124.7 144.6 L122.7 151 Z"
            />
            <g transform="translate(191 251) scale(1.45) translate(-191 -251)">
                <path
                    class="earth-connection__continent earth-connection__myanmar"
                    d="M197.7 248.5 L191.7 252.8 L194.3 259.2 L194.9 266.6 L193 274.7 L193 266.8 L190.9 259.2 L183.8 259.3 L182.9 253 L178.5 246.1 L181.2 240.2 L184.8 235 L189.6 229.8 L194.7 233.8 L194.9 241.2 L200.4 244.9 Z"
                />
            </g>

            <path class="earth-connection__route" d="M210 130 C204 166 197 207 191 250" />

            <circle class="earth-connection__particle" r="3">
                <animateMotion dur="2.4s" repeatCount="indefinite" path="M210 130 C204 166 197 207 191 250" />
            </circle>
            <circle class="earth-connection__particle earth-connection__particle--second" r="2.5">
                <animateMotion dur="2.4s" begin="-1.2s" repeatCount="indefinite" path="M210 130 C204 166 197 207 191 250" />
            </circle>

            <circle class="earth-connection__point earth-connection__point--russia" cx="210" cy="130" r="5" />
            <circle class="earth-connection__point earth-connection__point--myanmar" cx="191" cy="250" r="5" />

            <text class="earth-connection__map-label" x="210" y="117" text-anchor="middle">{{ $russia }}</text>
            <text class="earth-connection__map-label" x="191" y="237" text-anchor="middle">{{ $myanmar }}</text>
        </g>

        <circle class="earth-connection__rim" cx="210" cy="210" r="154" fill="none" />
        <path class="earth-connection__orbit" d="M34 213c0-86 77-155 176-155s176 69 176 155-77 149-176 149S34 299 34 213Z" />
    </svg>

    <p class="earth-connection__caption">{{ $text }}</p>
</div>
