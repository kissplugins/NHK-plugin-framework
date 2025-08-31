# NHK Event Manager Assets

This directory contains the frontend assets for the NHK Event Manager plugin.

## Structure

```
assets/
├── dist/           # Built assets (committed to repo)
│   ├── main.css    # Compiled Tailwind CSS
│   ├── index.js    # Compiled JavaScript bundle
│   └── index.js.map # Source map for debugging
├── src/            # Source files
│   ├── css/
│   │   └── main.css # Tailwind CSS source
│   └── js/
│       ├── index.js # Main JavaScript entry point
│       ├── api/     # API utilities
│       ├── components/ # Alpine.js components
│       └── machines/   # XState state machines
└── README.md       # This file
```

## Development

### Prerequisites

- [Bun](https://bun.sh/) (recommended) or Node.js 18+
- WordPress development environment

### Setup

1. Install dependencies:
   ```bash
   bun install
   # or
   npm install
   ```

2. Start development mode:
   ```bash
   bun run dev
   # or
   npm run dev
   ```

### Build Commands

- **Development**: `bun run dev` - Watch mode with live reloading
- **Production**: `bun run build` - Minified build for production
- **CSS only**: `bun run build:css` - Build only CSS
- **JS only**: `bun run build:js` - Build only JavaScript

### Technologies Used

- **Tailwind CSS** - Utility-first CSS framework
- **Alpine.js** - Lightweight JavaScript framework
- **XState** - State management for complex interactions
- **Bun** - Fast JavaScript runtime and bundler

## Production

The built assets in `/dist/` are committed to the repository and loaded by WordPress. No build process is required for end users.
