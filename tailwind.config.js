// tailwind.config.js

const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
    purge: [
      './resources/js/**/*.vue',
      './resources/js/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter var', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
    ]
}
