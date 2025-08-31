/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './assets/src/**/*.{html,js,ts}',
    './templates/**/*.php',
    './src/**/*.php'
  ],
  theme: {
    extend: {
      colors: {
        'nhk-primary': {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554'
        },
        'nhk-secondary': {
          50: '#faf5ff',
          100: '#f3e8ff',
          200: '#e9d5ff',
          300: '#d8b4fe',
          400: '#c084fc',
          500: '#a855f7',
          600: '#9333ea',
          700: '#7c3aed',
          800: '#6b21a8',
          900: '#581c87',
          950: '#3b0764'
        },
        'nhk-success': {
          50: '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#22c55e',
          600: '#16a34a',
          700: '#15803d',
          800: '#166534',
          900: '#14532d',
          950: '#052e16'
        },
        'nhk-warning': {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
          950: '#451a03'
        },
        'nhk-error': {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#ef4444',
          600: '#dc2626',
          700: '#b91c1c',
          800: '#991b1b',
          900: '#7f1d1d',
          950: '#450a0a'
        }
      },
      fontFamily: {
        'sans': ['Inter', 'system-ui', 'sans-serif'],
        'mono': ['JetBrains Mono', 'Consolas', 'monospace']
      },
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem'
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'bounce-slow': 'bounce 2s infinite'
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' }
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' }
        },
        slideDown: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' }
        }
      },
      boxShadow: {
        'nhk': '0 4px 6px -1px rgba(59, 130, 246, 0.1), 0 2px 4px -1px rgba(59, 130, 246, 0.06)',
        'nhk-lg': '0 10px 15px -3px rgba(59, 130, 246, 0.1), 0 4px 6px -2px rgba(59, 130, 246, 0.05)'
      }
    }
  },
  plugins: [
    require('@tailwindcss/forms')({
      strategy: 'class'
    }),
    require('@tailwindcss/typography'),
    // Custom plugin for NHK-specific utilities
    function({ addUtilities, addComponents, theme }) {
      const newUtilities = {
        '.text-balance': {
          'text-wrap': 'balance'
        },
        '.text-pretty': {
          'text-wrap': 'pretty'
        }
      }
      
      const newComponents = {
        '.btn-nhk': {
          padding: theme('spacing.2') + ' ' + theme('spacing.4'),
          borderRadius: theme('borderRadius.md'),
          fontWeight: theme('fontWeight.medium'),
          transition: 'all 0.2s ease-in-out',
          '&:focus': {
            outline: 'none',
            boxShadow: theme('boxShadow.nhk')
          }
        },
        '.btn-nhk-primary': {
          backgroundColor: theme('colors.nhk-primary.600'),
          color: theme('colors.white'),
          '&:hover': {
            backgroundColor: theme('colors.nhk-primary.700')
          },
          '&:disabled': {
            backgroundColor: theme('colors.gray.300'),
            cursor: 'not-allowed'
          }
        },
        '.btn-nhk-secondary': {
          backgroundColor: theme('colors.nhk-secondary.600'),
          color: theme('colors.white'),
          '&:hover': {
            backgroundColor: theme('colors.nhk-secondary.700')
          },
          '&:disabled': {
            backgroundColor: theme('colors.gray.300'),
            cursor: 'not-allowed'
          }
        },
        '.card-nhk': {
          backgroundColor: theme('colors.white'),
          borderRadius: theme('borderRadius.lg'),
          boxShadow: theme('boxShadow.md'),
          padding: theme('spacing.6'),
          border: '1px solid ' + theme('colors.gray.200')
        },
        '.input-nhk': {
          borderRadius: theme('borderRadius.md'),
          borderColor: theme('colors.gray.300'),
          '&:focus': {
            borderColor: theme('colors.nhk-primary.500'),
            boxShadow: '0 0 0 3px ' + theme('colors.nhk-primary.100')
          }
        }
      }
      
      addUtilities(newUtilities)
      addComponents(newComponents)
    }
  ],
  // Prefix for WordPress compatibility
  prefix: '',
  // Important for WordPress admin compatibility
  important: false,
  // Safelist for dynamic classes
  safelist: [
    'bg-nhk-primary-500',
    'bg-nhk-secondary-500',
    'bg-nhk-success-500',
    'bg-nhk-warning-500',
    'bg-nhk-error-500',
    'text-nhk-primary-600',
    'text-nhk-secondary-600',
    'border-nhk-primary-300',
    'border-nhk-secondary-300'
  ]
}
