import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import animate from "tailwindcss-animate";

// Barva iz CSS spremenljivke, ki podpira prosojnost (npr. bg-primary/15)
const v = (name) => `color-mix(in oklch, var(--${name}) calc(<alpha-value> * 100%), transparent)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx,ts,tsx}',
    ],

    theme: {
    	extend: {
    		fontFamily: {
    			sans: [
    				'Figtree',
                    ...defaultTheme.fontFamily.sans
                ]
    		},
    		borderRadius: {
    			lg: 'var(--radius)',
    			md: 'calc(var(--radius) - 2px)',
    			sm: 'calc(var(--radius) - 4px)'
    		},
    		colors: {
    			background: v('background'),
    			foreground: v('foreground'),
    			card: {
    				DEFAULT: v('card'),
    				foreground: v('card-foreground')
    			},
    			popover: {
    				DEFAULT: v('popover'),
    				foreground: v('popover-foreground')
    			},
    			primary: {
    				DEFAULT: v('primary'),
    				foreground: v('primary-foreground')
    			},
    			secondary: {
    				DEFAULT: v('secondary'),
    				foreground: v('secondary-foreground')
    			},
    			muted: {
    				DEFAULT: v('muted'),
    				foreground: v('muted-foreground')
    			},
    			accent: {
    				DEFAULT: v('accent'),
    				foreground: v('accent-foreground')
    			},
    			destructive: v('destructive'),
    			border: v('border'),
    			input: v('input'),
    			ring: v('ring'),
    			chart: {
    				'1': v('chart-1'),
    				'2': v('chart-2'),
    				'3': v('chart-3'),
    				'4': v('chart-4'),
    				'5': v('chart-5')
    			},
    			sidebar: {
    				DEFAULT: 'hsl(var(--sidebar-background))',
    				foreground: 'hsl(var(--sidebar-foreground))',
    				primary: 'hsl(var(--sidebar-primary))',
    				'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
    				accent: 'hsl(var(--sidebar-accent))',
    				'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
    				border: 'hsl(var(--sidebar-border))',
    				ring: 'hsl(var(--sidebar-ring))'
    			}
    		},
    		keyframes: {
    			'accordion-down': {
    				from: {
    					height: '0'
    				},
    				to: {
    					height: v('radix-accordion-content-height')
    				}
    			},
    			'accordion-up': {
    				from: {
    					height: v('radix-accordion-content-height')
    				},
    				to: {
    					height: '0'
    				}
    			}
    		},
    		animation: {
    			'accordion-down': 'accordion-down 0.2s ease-out',
    			'accordion-up': 'accordion-up 0.2s ease-out'
    		}
    	}
    },

    plugins: [forms, typography, animate],
};
