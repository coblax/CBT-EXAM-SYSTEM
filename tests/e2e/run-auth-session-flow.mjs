import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Auth & Session',
    specRelativePath: 'tests/e2e/auth-session.spec.js',
    fixtureKey: 'auth_session',
});
