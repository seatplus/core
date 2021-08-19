// tailwind.config.js

const defaultTheme = require('tailwindcss/defaultTheme')
const colors = require('tailwindcss/colors')

module.exports = {
    mode: 'jit',
    purge: [
      './resources/js/**/*.vue',
      './resources/js/**/*.js',
    ],
    theme: {
        colors: {
            ...colors,
            gray: colors.coolGray,
            coolGray: colors.gray
        },
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
