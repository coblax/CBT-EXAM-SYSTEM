import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Timer & Lifecycle',
    specRelativePath: 'tests/e2e/timer-lifecycle.spec.js',
    fixtureKey: 'timer_lifecycle',
});
