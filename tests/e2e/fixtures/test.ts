import { expect, test as base } from '@playwright/test';
import { resetDatabase } from '../helpers/db';

export const test = base;

test.beforeEach(() => {
    resetDatabase();
});

export { expect };
