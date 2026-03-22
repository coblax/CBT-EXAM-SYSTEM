export function createQuestionFlags(deps) {
    var state = deps.state;

    function isQuestionDoubtful(question) {
        if (!question) {
            return false;
        }
        return !!state.doubtful[question.id];
    }

    function isQuestionChanged(question) {
        if (!question) {
            return false;
        }
        return !!(state.changedQuestionLookup && state.changedQuestionLookup[question.id]);
    }

    function isQuestionRevisionMarked(question) {
        if (!question) {
            return false;
        }
        return !!(state.questionRevisionMarkerLookup && state.questionRevisionMarkerLookup[question.id]);
    }

    return {
        isQuestionChanged: isQuestionChanged,
        isQuestionRevisionMarked: isQuestionRevisionMarked,
        isQuestionDoubtful: isQuestionDoubtful
    };
}
