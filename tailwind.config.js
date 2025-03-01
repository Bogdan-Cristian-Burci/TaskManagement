import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors:{
                'colab-primary': '#4a0e5a',
                'colab-secondary': '#A78BFA',
                'colab-accent': '#e4bb6a',
                'colab-background': '#ecf0f1',
                'colab-text': '#374151'
            },
            backgroundImage: {
                'colab-gradient': 'linear-gradient(135deg, #A78BFA, #FAFAFA)',
                'custom-gradient': 'linear-gradient(135deg, #91e6d3, #e4bb6a, #a9f4b8 )',
                'colab-background': '#FAFAFA',
            },
        },
    },
    plugins: [],
};
