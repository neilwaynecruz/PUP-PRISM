import { existsSync, readFileSync, rmSync } from 'fs';
import {
    e2eServerStatePath,
    e2eTemplateDatabasePath,
    e2eTwoFactorFixturesPath,
} from './server-environment';

type ServerState = {
    pid: number;
    url: string;
};

export default function globalTeardown(): void {
    rmSync(e2eTemplateDatabasePath, { force: true });
    rmSync(e2eTwoFactorFixturesPath, { force: true });

    if (!existsSync(e2eServerStatePath)) {
        return;
    }

    const state = JSON.parse(
        readFileSync(e2eServerStatePath, 'utf8'),
    ) as ServerState;

    try {
        process.kill(state.pid);
    } catch (error) {
        const code = (error as NodeJS.ErrnoException).code;

        if (code !== 'ESRCH') {
            throw error;
        }
    } finally {
        rmSync(e2eServerStatePath, { force: true });
    }
}
