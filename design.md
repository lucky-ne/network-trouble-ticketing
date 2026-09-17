---
name: Zinc Precision
colors:
  surface: '#fcf8fb'
  surface-dim: '#dcd9dc'
  surface-bright: '#fcf8fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f2f5'
  surface-container: '#f0edf0'
  surface-container-high: '#eae7ea'
  surface-container-highest: '#e5e1e4'
  on-surface: '#1c1b1d'
  on-surface-variant: '#47464b'
  inverse-surface: '#313032'
  inverse-on-surface: '#f3f0f2'
  outline: '#77767b'
  outline-variant: '#c8c5cb'
  surface-tint: '#5f5e61'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#1b1b1e'
  on-primary-container: '#858387'
  inverse-primary: '#c8c5ca'
  secondary: '#5d5e66'
  on-secondary: '#ffffff'
  secondary-container: '#e3e1ec'
  on-secondary-container: '#63646c'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#1b1b1e'
  on-tertiary-container: '#848387'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e4e1e6'
  primary-fixed-dim: '#c8c5ca'
  on-primary-fixed: '#1b1b1e'
  on-primary-fixed-variant: '#47464a'
  secondary-fixed: '#e3e1ec'
  secondary-fixed-dim: '#c6c5cf'
  on-secondary-fixed: '#1a1b22'
  on-secondary-fixed-variant: '#46464e'
  tertiary-fixed: '#e4e1e5'
  tertiary-fixed-dim: '#c8c6c9'
  on-tertiary-fixed: '#1b1b1e'
  on-tertiary-fixed-variant: '#47464a'
  background: '#fcf8fb'
  on-background: '#1c1b1d'
  surface-variant: '#e5e1e4'
typography:
  display-lg:
    fontFamily: Geist
    fontSize: 36px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.03em
  display-lg-mobile:
    fontFamily: Geist
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 34px
    letterSpacing: -0.02em
  headline-xl:
    fontFamily: Geist
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Geist
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Geist
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Geist
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: -0.005em
  body-md:
    fontFamily: Geist
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0em
  body-sm:
    fontFamily: Geist
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
    letterSpacing: 0em
  metric-stat:
    fontFamily: Geist
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.03em
  label-md:
    fontFamily: Geist
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: -0.005em
  label-sm:
    fontFamily: Geist
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 14px
    letterSpacing: 0.02em
  code-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
    letterSpacing: 0em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  space-2xs: 0.125rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-base: 1rem
  space-lg: 1.25rem
  space-xl: 1.5rem
  space-2xl: 2rem
  space-3xl: 2.5rem
  sidebar-width: 16rem
  header-height: 3.5rem
---

## Brand & Style
This design system embodies a rigorous, hyper-focused corporate minimalism tailored for complex enterprise dashboards and mission-critical developer platforms. Taking inspiration directly from shadcn/ui, Linear, and Vercel, the design rejects gratuitous gradients, heavy shadows, and decorative noise in favor of radical clarity, density control, and structural discipline.

The visual language communicates authority, technical rigor, and zero-latency efficiency. Surfaces rely on high-precision border delimitation rather than dramatic drop shadows, creating an interface that behaves like an impeccably engineered technical instrument. The emotional posture is calm, unyielding, and focused on operational density.

## Colors
The color architecture is built around an uncompromising neutral monochromatic scale based on zinc tones, ensuring maximum semantic legibility and cognitive ease.

- **Canvas & Shell**: Pure white (`#ffffff`) serves as the elevated card and modal surface, placed over a deliberate neutral body canvas (`#f4f4f5` to `#fafafa`) to provide effortless baseline separation.
- **Structural Lines**: Borders and hairline dividers are strictly rendered in `#e4e4e7` (zinc-200), providing 1px optical boundaries between sections, tables, and nested panels.
- **Typography Scale**: Deep charcoal black (`#09090b`) delivers crisp, high-contrast headline and numerical readout presentation. Secondary body copy utilizes `#71717a` (zinc-500) and `#52525b` (zinc-600) for calm informational hierarchies.
- **Intentional Accents**: Primary interactive elements default to monolithic solid dark zinc (`#18181b`) with reverse white text. Semantic status colors (success, warning, destructive) exist solely as restrained 12px pill badges or subtle 2px micro-indicators, never overtaking the monochromatic canvas.

## Typography
Typography is driven by Geist, bringing geometric precision, clean optical kerning, and technical acuity. 

Tracking is deliberately tightened across headlines and numerical statistics (`-0.02em` to `-0.03em`) to mimic high-end technical instruments. Monospace elements utilize JetBrains Mono for commit hashes, IP addresses, tabular data indices, and API keys. Labels for uppercase micro-badges utilize slight positive letter-spacing (`0.02em`) to preserve legibility at compact enterprise scales.

## Layout & Spacing
The layout follows a continuous 8pt grid system with micro-increments of 4px and 2px for dense data structures and alignment.

- **Shell Architecture**: A fixed, collapsible sidebar fixed at `16rem` (256px) width, coupled with a sticky `3.5rem` (56px) utility header. The main workspace spans a responsive fluid canvas with a max-width constraint of `1440px` on ultra-wide viewports to maintain optimal reading scan lines.
- **Grid Structure**: Metric scorecards and analytic widgets utilize an adaptable 12-column CSS subgrid with strict `1rem` (16px) or `1.5rem` (24px) gutters. Cards reflow from single-column on mobile (<640px) to 2-column on tablet (<1024px) and 3-to-4 columns on desktop viewports.
- **Internal Component Rhythm**: Dense card bodies enforce `1.25rem` (20px) or `1.5rem` (24px) internal padding, while tables and item lists stick to compact `0.5rem` to `0.75rem` vertical cell paddings to maximize viewable data density.

## Elevation & Depth
In alignment with the shadcn/ui and Linear philosophy, depth is established via surface boundaries and ultra-subtle ambient drop shadows rather than heavy layering:

- **Surface Contrast**: Elevation level 0 rests on `#f4f4f5`. Elevated modules, data tables, and card widgets sit at elevation 1 with `#ffffff`.
- **Ghost Outlines**: Every container has a crisp, uniform 1px solid border (`#e4e4e7`).
- **Micro Ambient Shadows**: Cards use an extremely faint, multi-stop shadow: `0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04)`.
- **Flyouts & Overlays**: Popovers, command menus (`cmdk`), dropdowns, and dialogs apply a sharper utility shadow: `0 4px 6px -1px rgba(0, 0, 0, 0.06), 0 2px 4px -2px rgba(0, 0, 0, 0.04)`, bounded by a sharper border (`#d4d4d8`).

## Shapes
The design system adopts a refined "Soft" geometry (`roundedness: 1`). Radii are kept small and deliberate to maintain a crisp, architectural silhouette:

- **Inputs, Buttons, Badges**: Standard `rounded-md` (0.375rem / 6px) to match classic Radix and shadcn primitive defaults.
- **Cards & Data Panels**: `rounded-lg` (0.5rem / 8px) with 1px borders, preserving clean external silhouettes without excessive curvature.
- **Modal Dialogs & Floating Command Menus**: `rounded-xl` (0.75rem / 12px) for contained structural elevation.
- **Status Dots & Avatars**: Circular (`rounded-full`) exclusively used for user profiles and live operational indicators.

## Components

### Buttons
- **Primary**: Solid zinc-900 (`#18181b`), pure white label, `rounded-md`, 36px height (`h-9`), padding `px-4`, font size 13px with font weight 500. Subtle hover transition to zinc-800 (`#27272a`).
- **Secondary / Outline**: White surface, 1px border in `#e4e4e7`, text `#18181b`. Hover state applies `#f4f4f5` background with zero border jump.
- **Ghost**: Transparent background, text `#71717a`, hover to `#f4f4f5` with text `#09090b`.

### Data Cards & Metric Displays
- Clean white surfaces with 1px border (`#e4e4e7`). 
- Card headers feature compact 13px muted labels (`#71717a`) and optional micro icon slots (16x16px).
- Value displays utilize high-impact 28px bold metrics (`#09090b`) with a sub-label or secondary percentage trend badge positioned immediately below.

### Inputs & Form Elements
- **Input Fields**: 36px height, white background, 1px border (`#e4e4e7`), `rounded-md`, horizontal padding `0.75rem`. Placeholder text rendered in `#a1a1aa`. Active focus state drops a sharp 1px ring in zinc-950 (`#09090b`) with no fuzzy outer spread.
- **Checkboxes & Radios**: 16x16px squares/circles with `#e4e4e7` border. When checked, filled with `#18181b` displaying crisp white glyphs.

### Status Chips & Badges
- Ultra-compact, 20px height, padding `px-2`, `rounded-md` or `rounded-full`.
- Neutral badge: `#f4f4f5` background, `#18181b` text, hairline border in `#e4e4e7`.
- Live Status dot badges include a 6px solid circular pulse next to uppercase 11px category copy.

### Tables & Data Grids
- Flat edge-to-edge tables wrapped within an outer 1px bordered card container.
- Table headers: `#fafafa` background, 12px uppercase medium text (`#71717a`), height 38px, with borders on row dividers only.
- Table rows: 48px standard row height, smooth hover transition to `#f9fafb`, tabular figures enabled (`font-variant-numeric: tabular-nums`).

### Command Menus & Flyouts
- Floating central command palette inspired by Linear/Spotlight (`max-w-xl`), using a subtle backdrop blur on the overlay scrim.
- Integrated search input separated by a continuous horizontal 1px divider, with group labels in 11px uppercase bold muted text.