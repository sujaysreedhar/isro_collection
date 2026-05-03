<style>
    .subject-atlas {
        --atlas-paper: color-mix(in srgb, var(--color-surface, #ffffff) 90%, #f3ecdf 10%);
        --atlas-paper-strong: color-mix(in srgb, var(--color-surface, #ffffff) 72%, #efe2c7 28%);
        --atlas-paper-soft: color-mix(in srgb, var(--color-surface, #ffffff) 94%, #f8f2e6 6%);
        --atlas-ink: color-mix(in srgb, var(--color-primary, #111827) 88%, #25180c 12%);
        --atlas-muted: color-mix(in srgb, var(--color-text-muted, #6b7280) 78%, #6b4d26 22%);
        --atlas-line: color-mix(in srgb, var(--color-border, #e5e7eb) 64%, #b08954 36%);
        --atlas-accent: color-mix(in srgb, var(--color-accent, #2563eb) 45%, #9a5c14 55%);
        --atlas-shadow: 0 24px 80px -34px rgba(31, 24, 14, 0.38);
    }

    .subject-atlas-bleed {
        width: 100vw;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
    }

    .subject-atlas-shell {
        max-width: 84rem;
        margin: 0 auto;
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .subject-atlas-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.26em;
        text-transform: uppercase;
        color: var(--atlas-accent);
    }

    .subject-atlas-kicker::before {
        content: "";
        width: 3.25rem;
        height: 1px;
        background: currentColor;
        opacity: 0.7;
    }

    .subject-atlas-hero {
        position: relative;
        overflow: hidden;
        color: var(--atlas-ink);
        background:
            radial-gradient(circle at top left, color-mix(in srgb, var(--atlas-accent) 20%, transparent) 0, transparent 34%),
            linear-gradient(135deg, color-mix(in srgb, var(--atlas-paper, #fff) 92%, #f7f0df 8%) 0%, color-mix(in srgb, var(--atlas-paper-strong, #fff) 72%, #e7d4b4 28%) 100%);
        border-top: 1px solid var(--atlas-line);
        border-bottom: 1px solid var(--atlas-line);
    }

    .subject-atlas-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-image:
            linear-gradient(to right, rgba(125, 91, 39, 0.08) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(125, 91, 39, 0.08) 1px, transparent 1px);
        background-size: 4.75rem 4.75rem;
        mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), transparent 82%);
    }

    .subject-atlas-hero-grid {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 2rem;
        padding-top: 4rem;
        padding-bottom: 4rem;
    }

    .subject-atlas-display {
        max-width: 52rem;
        font-family: var(--font-heading, 'Playfair Display', serif);
        font-size: clamp(3rem, 7vw, 5.75rem);
        line-height: 0.94;
        letter-spacing: -0.045em;
        color: var(--atlas-ink);
    }

    .subject-atlas-lead {
        max-width: 43rem;
        margin-top: 1.5rem;
        font-size: 1.08rem;
        line-height: 1.8;
        color: var(--atlas-muted);
    }

    .subject-atlas-meta {
        display: grid;
        gap: 0.85rem;
        margin-top: 2rem;
    }

    .subject-atlas-meta-block {
        padding: 1rem 1.1rem;
        border: 1px solid var(--atlas-line);
        background: rgba(255, 255, 255, 0.46);
        backdrop-filter: blur(3px);
        box-shadow: var(--atlas-shadow);
    }

    .subject-atlas-meta-label {
        display: block;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.24em;
        text-transform: uppercase;
        color: var(--atlas-muted);
    }

    .subject-atlas-meta-value {
        display: block;
        margin-top: 0.45rem;
        font-family: var(--font-heading, 'Playfair Display', serif);
        font-size: clamp(2rem, 4vw, 3rem);
        line-height: 1;
        color: var(--atlas-ink);
    }

    .subject-atlas-section {
        padding-top: 4.5rem;
    }

    .subject-atlas-section-head {
        display: grid;
        gap: 0.8rem;
        margin-bottom: 1.75rem;
    }

    .subject-atlas-title {
        font-family: var(--font-heading, 'Playfair Display', serif);
        font-size: clamp(2rem, 4vw, 3.2rem);
        line-height: 1;
        color: var(--atlas-ink);
    }

    .subject-atlas-copy {
        max-width: 42rem;
        font-size: 0.98rem;
        line-height: 1.8;
        color: var(--atlas-muted);
    }

    .subject-atlas-panel {
        position: relative;
        padding: 1.5rem;
        border: 1px solid var(--atlas-line);
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(247, 240, 225, 0.68));
        box-shadow: var(--atlas-shadow);
    }

    .subject-atlas-panel::before {
        content: "";
        position: absolute;
        inset: 0.8rem;
        border: 1px dashed rgba(125, 91, 39, 0.18);
        pointer-events: none;
    }

    .subject-atlas-stack {
        display: grid;
        gap: 1.25rem;
    }

    .subject-atlas-band {
        display: grid;
        gap: 1.25rem;
        align-items: start;
        padding: 1.4rem;
        border: 1px solid var(--atlas-line);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.84), rgba(247, 240, 225, 0.58));
        transition: transform 0.22s ease, border-color 0.22s ease, background 0.22s ease;
    }

    .subject-atlas-band:hover {
        transform: translateY(-2px);
        border-color: var(--atlas-accent);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(244, 232, 210, 0.84));
    }

    .subject-atlas-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3.6rem;
        min-height: 3.6rem;
        padding: 0.5rem;
        border: 1px solid var(--atlas-line);
        font-family: var(--font-heading, 'Playfair Display', serif);
        font-size: 1.2rem;
        color: var(--atlas-accent);
        background: rgba(255, 255, 255, 0.66);
    }

    .subject-atlas-band-title {
        font-family: var(--font-heading, 'Playfair Display', serif);
        font-size: clamp(1.65rem, 2vw, 2.15rem);
        line-height: 1.05;
        color: var(--atlas-ink);
    }

    .subject-atlas-band-copy {
        margin-top: 0.7rem;
        max-width: 42rem;
        color: var(--atlas-muted);
        line-height: 1.8;
    }

    .subject-atlas-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-top: 1rem;
    }

    .subject-atlas-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.42rem 0.8rem;
        border: 1px solid rgba(125, 91, 39, 0.18);
        font-size: 0.82rem;
        color: var(--atlas-muted);
        background: rgba(255, 255, 255, 0.54);
    }

    .subject-atlas-chip strong {
        color: var(--atlas-accent);
        font-weight: 700;
    }

    .subject-atlas-link {
        display: inline-flex;
        align-items: center;
        gap: 0.7rem;
        margin-top: 1.25rem;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--atlas-ink);
    }

    .subject-atlas-link svg {
        width: 1rem;
        height: 1rem;
        color: var(--atlas-accent);
        transition: transform 0.22s ease;
    }

    .subject-atlas-band:hover .subject-atlas-link svg,
    .subject-atlas-item:hover .subject-atlas-link svg {
        transform: translateX(0.18rem);
    }

    .subject-atlas-index {
        columns: 1;
        column-gap: 1.5rem;
    }

    .subject-atlas-index a {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.78rem 0;
        border-bottom: 1px solid rgba(125, 91, 39, 0.14);
        color: var(--atlas-ink);
        break-inside: avoid;
    }

    .subject-atlas-index a:hover .subject-atlas-index-name {
        color: var(--atlas-accent);
    }

    .subject-atlas-index-name {
        line-height: 1.45;
        transition: color 0.2s ease;
    }

    .subject-atlas-index-count {
        flex-shrink: 0;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--atlas-muted);
    }

    .subject-atlas-breadcrumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--atlas-muted);
    }

    .subject-atlas-breadcrumbs a {
        color: var(--atlas-muted);
        transition: color 0.2s ease;
    }

    .subject-atlas-breadcrumbs a:hover,
    .subject-atlas-breadcrumbs .is-current {
        color: var(--atlas-accent);
    }

    .subject-atlas-grid {
        display: grid;
        gap: 1.25rem;
    }

    .subject-atlas-item {
        display: grid;
        overflow: hidden;
        border: 1px solid var(--atlas-line);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(246, 238, 223, 0.72));
        box-shadow: var(--atlas-shadow);
        transition: transform 0.22s ease, border-color 0.22s ease;
    }

    .subject-atlas-item:hover {
        transform: translateY(-3px);
        border-color: var(--atlas-accent);
    }

    .subject-atlas-media {
        position: relative;
        min-height: 15rem;
        background:
            linear-gradient(135deg, rgba(193, 160, 108, 0.12), rgba(62, 48, 22, 0.08)),
            color-mix(in srgb, var(--atlas-paper-soft) 72%, #c2b59b 28%);
    }

    .subject-atlas-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.45s ease;
    }

    .subject-atlas-item:hover .subject-atlas-media img {
        transform: scale(1.04);
    }

    .subject-atlas-reg {
        position: absolute;
        left: 1rem;
        bottom: 1rem;
        padding: 0.45rem 0.65rem;
        border: 1px solid rgba(125, 91, 39, 0.18);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--atlas-ink);
        background: rgba(255, 255, 255, 0.86);
        backdrop-filter: blur(4px);
    }

    .subject-atlas-item-copy {
        display: grid;
        gap: 0.9rem;
        padding: 1.25rem;
    }

    .subject-atlas-item-date {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--atlas-accent);
    }

    .subject-atlas-item-title {
        font-family: var(--font-heading, 'Playfair Display', serif);
        font-size: 1.5rem;
        line-height: 1.08;
        color: var(--atlas-ink);
    }

    .subject-atlas-empty {
        padding: 3rem 1.5rem;
        text-align: center;
        border: 1px solid var(--atlas-line);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(246, 238, 223, 0.74));
        box-shadow: var(--atlas-shadow);
    }

    .subject-atlas-empty svg {
        margin: 0 auto;
        width: 3rem;
        height: 3rem;
        color: var(--atlas-muted);
        opacity: 0.45;
    }

    @media (min-width: 768px) {
        .subject-atlas-hero-grid {
            grid-template-columns: minmax(0, 1.7fr) minmax(16rem, 0.7fr);
            align-items: end;
            gap: 2.5rem;
            padding-top: 5.5rem;
            padding-bottom: 5rem;
        }

        .subject-atlas-meta {
            margin-top: 0;
        }

        .subject-atlas-band {
            grid-template-columns: auto minmax(0, 1fr);
            gap: 1.5rem;
        }

        .subject-atlas-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .subject-atlas-index {
            columns: 2;
        }
    }

    @media (min-width: 1280px) {
        .subject-atlas-grid.subject-atlas-grid-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .subject-atlas-index {
            columns: 3;
        }
    }
</style>
