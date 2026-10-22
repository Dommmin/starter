export default {
    extends: ['@commitlint/config-conventional'],
    // Dependabot generates its own messages (often over 72 characters).
    ignores: [(message) => message.includes('Signed-off-by: dependabot[bot]')],
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
