export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'header-max-length': [2, 'always', 72],
        'type-enum': [
            2,
            'always',
            [
                'feat',
                'fix',
                'refactor',
                'perf',
                'test',
                'docs',
                'build',
                'ci',
                'chore',
                'style',
                'revert',
            ],
        ],
        'type-case': [2, 'always', 'lower-case'],
        'subject-empty': [2, 'never'],
        'subject-full-stop': [2, 'never', '.'],
        'subject-case': [0],
    },
};
