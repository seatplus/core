// tailwind.config.js

const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
    purge: [
        './**/*.vue'
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter var', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [
        require('@tailwindcss/ui')({
            layout: 'sidebar',
        })
    ]
}
