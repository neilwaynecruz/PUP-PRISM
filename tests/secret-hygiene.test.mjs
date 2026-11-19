import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const projectRoot = fileURLToPath(new URL('../', import.meta.url));

function git(args, input) {
    const result = spawnSync('git', args, {
        cwd: projectRoot,
        encoding: 'utf8',
        input,
        windowsHide: true,
    });

    assert.ifError(result.error);

    return result;
}

test('local credential files and the previously leaked key-note file are ignored', () => {
    const paths = [
        '.env',
        '.env.local',
        '.env.presentation.local',
        'resend_api.md',
    ];
    const result = git(
        ['check-ignore', '--no-index', '--stdin'],
        `${paths.join('\n')}\n`,
    );

    assert.equal(result.status, 0);
    assert.deepEqual(result.stdout.trim().split(/\r?\n/), paths);
});

test('shared environment templates and application source remain visible to Git', () => {
    const result = git(
        ['check-ignore', '--no-index', '--stdin'],
        '.env.example\n.env.testing\n.env.e2e\napp/Services/Operations/SystemHealthService.php\n',
    );

    assert.equal(result.status, 1);
    assert.equal(result.stdout, '');
});

for (const cached of [false, true]) {
    test(`no Resend credential candidates exist in the ${cached ? 'Git index' : 'tracked working files'}`, () => {
        const result = git([
            'grep',
            ...(cached ? ['--cached'] : []),
            '-I',
            '-l',
            '-E',
            '(^|[^[:alnum:]_])re_[[:alnum:]_]{20,}',
            '--',
            '.',
        ]);

        assert.ok(
            result.status === 0 || result.status === 1,
            'Git credential scan could not complete.',
        );
        assert.equal(
            result.stdout,
            '',
            `Possible Resend credentials found in these paths (values withheld):\n${result.stdout}`,
        );
    });
}
