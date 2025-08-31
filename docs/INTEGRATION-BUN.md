# Bun.js Integration with NHK Framework

## 🚀 Overview

The NHK Framework leverages **Bun.js** as its primary JavaScript runtime and build tool, providing ultra-fast package management, bundling, and development workflows. This integration demonstrates how modern JavaScript tooling can be seamlessly incorporated into WordPress plugin development.

## ✅ What We Implemented

### 🔧 **Build System Architecture**

Bun.js serves as our complete frontend toolchain:
- **Package Manager**: Lightning-fast dependency installation
- **Bundler**: Native ES module bundling with tree shaking
- **Task Runner**: Concurrent development and production workflows
- **TypeScript Support**: Built-in TypeScript compilation
- **WordPress Integration**: Proper external dependency handling

### 📦 **Package Configuration**

Our `package.json` is optimized for Bun with WordPress-specific considerations:

```json
{
  "name": "nhk-event-manager",
  "version": "1.0.0",
  "description": "Modern frontend enhancements for NHK Event Manager",
  "main": "assets/dist/index.js",
  "scripts": {
    "dev": "NODE_ENV=development concurrently \"bun run watch:css\" \"bun run watch:js\"",
    "build": "NODE_ENV=production bun run build:css && bun run build:js",
    "watch:css": "bunx tailwindcss -i ./assets/src/css/main.css -o ./assets/dist/main.css --watch",
    "watch:js": "bun build ./assets/src/js/index.js --outdir=./assets/dist --watch --sourcemap=inline",
    "build:css": "bunx tailwindcss -i ./assets/src/css/main.css -o ./assets/dist/main.css --minify",
    "build:js": "bun build ./assets/src/js/index.js --outdir=./assets/dist --format=iife --minify --external:wp-* --sourcemap=external",
    "test": "jest"
  },
  "dependencies": {
    "alpinejs": "^3.14.0",
    "xstate": "^5.11.0"
  },
  "devDependencies": {
    "tailwindcss": "^3.4.0",
    "concurrently": "^8.2.0",
    "jest": "^29.7.0",
    "@types/alpinejs": "^3.13.0",
    "typescript": "^5.3.0",
    "eslint": "^8.57.0",
    "@tailwindcss/forms": "^0.5.7",
    "@tailwindcss/typography": "^0.5.10"
  },
  "engines": {
    "node": ">=18.0.0",
    "bun": ">=1.0.0"
  }
}
```

### ⚡ **Performance Results**

**Installation Speed:**
```bash
bun install
# Result: 492 packages installed [2.76s]
# vs npm: ~8-12 seconds for same packages
```

**Build Performance:**
```bash
bun run build
# CSS: Done in 257ms (Tailwind compilation)
# JS: Bundled 17 modules in 9ms (Alpine.js + XState)
# Total: <1 second for complete build
```

**Generated Assets:**
- `assets/dist/main.css` - Optimized Tailwind CSS
- `assets/dist/index.js` - Bundled JavaScript (119KB minified)
- `assets/dist/index.js.map` - Source maps for debugging

## 🔧 How to Use Bun with NHK Framework

### **Step 1: Install Bun**

```bash
# Install Bun globally
curl -fsSL https://bun.sh/install | bash

# Reload your shell
exec $SHELL

# Verify installation
bun --version
```

### **Step 2: Project Setup**

```bash
# Clone or create your NHK Framework project
cd your-nhk-project

# Install dependencies with Bun
bun install

# This installs all packages from package.json incredibly fast
```

### **Step 3: Development Workflow**

```bash
# Start development mode with file watching
bun run dev
# This runs both CSS and JS watchers concurrently

# Or run individual watchers
bun run watch:css    # Tailwind CSS compilation
bun run watch:js     # JavaScript bundling with hot reload
```

### **Step 4: Production Build**

```bash
# Build optimized assets for production
bun run build

# This creates:
# - assets/dist/main.css (minified Tailwind)
# - assets/dist/index.js (minified bundle)
# - assets/dist/index.js.map (source maps)
```

### **Step 5: WordPress Integration**

The `AssetManager.php` class automatically handles Bun-built assets:

```php
// Automatic asset loading with cache busting
$asset_manager = new AssetManager($container);
$asset_manager->init();

// Assets are conditionally loaded based on:
// - Current page context
// - File existence checks
// - Development vs production mode
```

## 🎯 **Key Features & Benefits**

### **1. Ultra-Fast Package Management**
- **3x faster** than npm for installations
- **Efficient caching** reduces repeated downloads
- **Lockfile compatibility** with npm/yarn projects

### **2. Native ES Module Support**
- **Tree shaking** eliminates unused code automatically
- **Modern JavaScript** features without transpilation overhead
- **Import/Export** syntax works natively

### **3. WordPress-Specific Optimizations**
- **External dependencies** (`--external:wp-*`) exclude WordPress globals
- **IIFE format** prevents global scope pollution
- **Proper enqueuing** through WordPress asset system

### **4. Development Experience**
- **Hot reload** for instant feedback during development
- **Source maps** for debugging production builds
- **TypeScript support** without additional configuration
- **Concurrent tasks** for CSS and JS watching

### **5. Production Ready**
- **Minification** reduces bundle sizes significantly
- **Cache busting** through file modification times
- **Asset optimization** with proper compression
- **Error handling** for missing or failed builds

## 📁 **Project Structure**

```
your-nhk-project/
├── package.json              # Bun configuration
├── bun.lockb                 # Bun lockfile (binary format)
├── assets/
│   ├── src/
│   │   ├── js/
│   │   │   ├── index.js      # Main entry point
│   │   │   ├── components/   # Alpine.js components
│   │   │   └── machines/     # XState machines
│   │   └── css/
│   │       └── main.css      # Tailwind entry point
│   └── dist/                 # Built assets (generated)
│       ├── main.css
│       ├── index.js
│       └── index.js.map
├── src/
│   └── Core/
│       └── AssetManager.php  # WordPress integration
└── tailwind.config.js       # Tailwind configuration
```

## 🔄 **Development Commands**

### **Daily Development**
```bash
# Start development environment
bun run dev

# Run tests
bun run test

# Lint JavaScript
bun run lint:js
```

### **Building & Deployment**
```bash
# Production build
bun run build

# Build only CSS
bun run build:css

# Build only JavaScript
bun run build:js
```

### **Package Management**
```bash
# Add new dependency
bun add package-name

# Add dev dependency
bun add -d package-name

# Remove package
bun remove package-name

# Update all packages
bun update
```

## 🚀 **Advanced Configuration**

### **Custom Build Options**

You can customize the Bun build process in `package.json`:

```json
{
  "scripts": {
    "build:js": "bun build ./assets/src/js/index.js --outdir=./assets/dist --format=iife --minify --external:wp-* --sourcemap=external --target=browser --splitting"
  }
}
```

**Available Options:**
- `--format=iife` - Immediately Invoked Function Expression (WordPress compatible)
- `--minify` - Compress output for production
- `--external:wp-*` - Exclude WordPress globals
- `--sourcemap=external` - Generate separate source map files
- `--target=browser` - Optimize for browser environment
- `--splitting` - Enable code splitting for larger apps

### **Environment-Specific Builds**

```bash
# Development build with debugging
NODE_ENV=development bun run build

# Production build with optimizations
NODE_ENV=production bun run build

# Staging build with source maps
NODE_ENV=staging bun run build
```

## 🔍 **Troubleshooting**

### **Common Issues**

**1. Bun not found after installation**
```bash
# Reload shell environment
exec $SHELL
# or
source ~/.bashrc  # or ~/.zshrc
```

**2. WordPress globals not excluded**
```bash
# Ensure --external:wp-* is in build script
bun build --external:wp-api-fetch --external:wp-element
```

**3. Assets not loading in WordPress**
```php
// Check file existence in AssetManager
if (!file_exists($asset_path)) {
    error_log("Asset not found: $asset_path");
}
```

### **Performance Tips**

1. **Use `.bunfig.toml`** for project-specific Bun configuration
2. **Enable caching** for faster subsequent builds
3. **Optimize imports** to reduce bundle size
4. **Use dynamic imports** for code splitting when needed

## 📊 **Comparison with Other Tools**

| Feature | Bun | Webpack | Vite | Parcel |
|---------|-----|---------|------|--------|
| Install Speed | ⚡⚡⚡ | ⚡ | ⚡⚡ | ⚡⚡ |
| Build Speed | ⚡⚡⚡ | ⚡ | ⚡⚡ | ⚡⚡ |
| Configuration | ⚡⚡⚡ | ⚡ | ⚡⚡ | ⚡⚡⚡ |
| WordPress Integration | ⚡⚡⚡ | ⚡⚡ | ⚡⚡ | ⚡⚡ |
| TypeScript Support | ⚡⚡⚡ | ⚡⚡ | ⚡⚡⚡ | ⚡⚡ |

## 🎉 **Conclusion**

Bun.js integration with the NHK Framework provides:

✅ **Ultra-fast development** with sub-second builds  
✅ **Modern JavaScript features** without complexity  
✅ **WordPress compatibility** with proper asset handling  
✅ **Production-ready optimization** with minimal configuration  
✅ **Developer experience** that scales from simple to complex projects  

This integration demonstrates how cutting-edge JavaScript tooling can enhance WordPress development while maintaining compatibility and performance standards.

---

**Next Steps:**
1. Install Bun and try the development workflow
2. Customize build scripts for your specific needs
3. Explore advanced features like code splitting and dynamic imports
4. Integrate with your existing WordPress development process
