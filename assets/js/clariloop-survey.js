(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.clariloop-survey-container');
        if (!container || !window.clariloopSurveyConfig) {
            return;
        }

        const clariloopConfig = window.clariloopSurveyConfig;

        Clariloop.renderSurvey({
            apiKey: clariloopConfig.apiKey,
            customerId: clariloopConfig.customerId,
            orderId: clariloopConfig.orderId,
            email: clariloopConfig.email,
            containerId: 'clariloop-survey-container',
            displayMode: clariloopConfig.displayMode || 'inline',
            position: clariloopConfig.position,
        });
    });
})();
