(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.clariloop-survey-container');
        if (!container || !window.clariloopSurveyConfig) {
            return;
        }

        Clariloop.renderSurvey({
            apiKey: window.clariloopSurveyConfig.apiKey,
            customerId: window.clariloopSurveyConfig.customerId,
            orderId: window.clariloopSurveyConfig.orderId,
            email: window.clariloopSurveyConfig.email,
            containerId: 'clariloop-survey-container',
            displayMode: window.clariloopSurveyConfig.displayMode || 'inline'
        });
    });
})();