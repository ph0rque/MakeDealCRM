<?php
/**
 * Test file for Pipeline Control Buttons
 * Run this directly to test button functionality without SuiteCRM
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pipeline Control Buttons Test</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/pipeline.css">
    <link rel="stylesheet" href="css/pipeline-focus.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/pipeline.js"></script>
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .test-info { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .test-info h3 { margin-top: 0; }
        .state-display { margin-top: 10px; }
        .state-display table { width: 100%; }
        .state-display td { padding: 5px; border: 1px solid #ddd; }
        .state-display .label-cell { background: #f0f0f0; font-weight: bold; width: 40%; }
        .test-results { margin-top: 20px; }
        .test-result { padding: 10px; margin: 5px 0; border-radius: 3px; }
        .test-pass { background: #dff0d8; color: #3c763d; }
        .test-fail { background: #f2dede; color: #a94442; }
        .test-warn { background: #fcf8e3; color: #8a6d3b; }
    </style>
</head>
<body>
    <h1>Pipeline Control Buttons Test Suite</h1>
    
    <div class="test-info">
        <h3>Test Controls</h3>
        <button class="btn btn-success" onclick="runAllTests()">Run All Tests</button>
        <button class="btn btn-warning" onclick="clearLocalStorage()">Clear LocalStorage</button>
        <button class="btn btn-info" onclick="location.reload()">Reload Page</button>
        
        <div class="test-results" id="test-results"></div>
    </div>

    <div id="pipeline-container" class="pipeline-container">
        <div class="pipeline-header">
            <h2>Deal Pipeline</h2>
            <div class="pipeline-actions">
                <button class="btn btn-primary btn-sm" onclick="PipelineView.refreshBoard()">
                    <span class="glyphicon glyphicon-refresh"></span> Refresh
                </button>
                <button class="btn btn-default btn-sm" onclick="PipelineView.toggleCompactView()">
                    <span class="glyphicon glyphicon-resize-small"></span> Compact View
                </button>
                <button class="btn btn-info btn-sm" onclick="PipelineView.toggleFocusFilter()" id="focus-filter-btn">
                    <span class="glyphicon glyphicon-star"></span> <span id="focus-filter-text">Show Focused</span>
                </button>
            </div>
        </div>
        
        <div class="pipeline-board-wrapper">
            <div class="pipeline-board">
                <div class="pipeline-stage" data-stage="prospecting">
                    <div class="stage-header">
                        <h3>Prospecting</h3>
                        <div class="stage-stats">
                            <span class="deal-count">3</span>
                        </div>
                    </div>
                    <div class="stage-body droppable" data-stage="prospecting">
                        <div class="deal-card draggable" data-deal-id="1" data-stage="prospecting">
                            <div class="deal-card-header">
                                <h4 class="deal-name">Test Deal 1</h4>
                                <div class="deal-card-actions">
                                    <button class="focus-toggle-btn" onclick="PipelineView.toggleFocus('1', true); event.stopPropagation();">
                                        <span class="glyphicon glyphicon-star-empty"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="deal-card-body">
                                <div class="deal-amount">$10,000</div>
                            </div>
                        </div>
                        <div class="deal-card draggable focused-deal" data-deal-id="2" data-stage="prospecting" data-focused="true">
                            <div class="deal-card-header">
                                <h4 class="deal-name">Focused Deal</h4>
                                <div class="deal-card-actions">
                                    <button class="focus-toggle-btn active" onclick="PipelineView.toggleFocus('2', false); event.stopPropagation();">
                                        <span class="glyphicon glyphicon-star"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="deal-card-body">
                                <div class="deal-amount">$25,000</div>
                            </div>
                        </div>
                        <div class="deal-card draggable" data-deal-id="3" data-stage="prospecting">
                            <div class="deal-card-header">
                                <h4 class="deal-name">Test Deal 3</h4>
                                <div class="deal-card-actions">
                                    <button class="focus-toggle-btn" onclick="PipelineView.toggleFocus('3', true); event.stopPropagation();">
                                        <span class="glyphicon glyphicon-star-empty"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="deal-card-body">
                                <div class="deal-amount">$15,000</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="test-info">
        <h3>Current State</h3>
        <div class="state-display">
            <table>
                <tr>
                    <td class="label-cell">Compact View State</td>
                    <td id="compact-state">-</td>
                </tr>
                <tr>
                    <td class="label-cell">Focus Filter State</td>
                    <td id="focus-state">-</td>
                </tr>
                <tr>
                    <td class="label-cell">LocalStorage: compact_view</td>
                    <td id="ls-compact">-</td>
                </tr>
                <tr>
                    <td class="label-cell">LocalStorage: focus_filter</td>
                    <td id="ls-focus">-</td>
                </tr>
                <tr>
                    <td class="label-cell">Visible Deal Cards</td>
                    <td id="visible-cards">-</td>
                </tr>
                <tr>
                    <td class="label-cell">Container Classes</td>
                    <td id="container-classes">-</td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // Initialize PipelineView
        jQuery(document).ready(function() {
            // Mock the missing functions
            PipelineView.showLoading = function() { console.log('Loading...'); };
            PipelineView.hideLoading = function() { console.log('Loading complete'); };
            PipelineView.showNotification = function(msg, type) { 
                console.log(type.toUpperCase() + ': ' + msg);
                addTestResult(msg, type === 'error' ? 'fail' : 'pass');
            };
            PipelineView.refreshBoard = function() { 
                this.showNotification('Refresh clicked (mocked)', 'success');
                updateStateDisplay();
            };
            
            PipelineView.init({
                currentUserId: 'test-user',
                isMobile: false,
                updateUrl: '#',
                refreshUrl: '#'
            });
            
            updateStateDisplay();
        });

        function updateStateDisplay() {
            document.getElementById('compact-state').textContent = PipelineView.config.compactView;
            document.getElementById('focus-state').textContent = PipelineView.config.focusFilterActive;
            document.getElementById('ls-compact').textContent = localStorage.getItem('pipeline_compact_view') || 'null';
            document.getElementById('ls-focus').textContent = localStorage.getItem('pipeline_focus_filter') || 'null';
            document.getElementById('visible-cards').textContent = jQuery('.deal-card:visible').length;
            document.getElementById('container-classes').textContent = document.getElementById('pipeline-container').className;
        }

        function clearLocalStorage() {
            localStorage.removeItem('pipeline_compact_view');
            localStorage.removeItem('pipeline_focus_filter');
            addTestResult('LocalStorage cleared', 'warn');
            updateStateDisplay();
        }

        function addTestResult(message, type) {
            var resultDiv = document.createElement('div');
            resultDiv.className = 'test-result test-' + type;
            resultDiv.textContent = message;
            document.getElementById('test-results').appendChild(resultDiv);
        }

        function runAllTests() {
            document.getElementById('test-results').innerHTML = '';
            
            // Test 1: Compact View Toggle
            var initialCompact = PipelineView.config.compactView;
            PipelineView.toggleCompactView();
            var test1Pass = PipelineView.config.compactView === !initialCompact;
            addTestResult('Test 1 - Compact View Toggle: ' + (test1Pass ? 'PASS' : 'FAIL'), test1Pass ? 'pass' : 'fail');
            
            // Test 2: Compact View Button State
            var compactBtn = jQuery('.pipeline-actions button:has(.glyphicon-resize-small)').first();
            var test2Pass = (PipelineView.config.compactView && compactBtn.hasClass('active')) || 
                           (!PipelineView.config.compactView && !compactBtn.hasClass('active'));
            addTestResult('Test 2 - Compact Button Active State: ' + (test2Pass ? 'PASS' : 'FAIL'), test2Pass ? 'pass' : 'fail');
            
            // Test 3: Compact View LocalStorage
            var lsCompact = localStorage.getItem('pipeline_compact_view');
            var test3Pass = lsCompact === (PipelineView.config.compactView ? 'true' : 'false');
            addTestResult('Test 3 - Compact LocalStorage: ' + (test3Pass ? 'PASS' : 'FAIL'), test3Pass ? 'pass' : 'fail');
            
            // Test 4: Focus Filter Toggle
            var initialFocus = PipelineView.config.focusFilterActive;
            PipelineView.toggleFocusFilter();
            var test4Pass = PipelineView.config.focusFilterActive === !initialFocus;
            addTestResult('Test 4 - Focus Filter Toggle: ' + (test4Pass ? 'PASS' : 'FAIL'), test4Pass ? 'pass' : 'fail');
            
            // Test 5: Focus Filter Button State
            var focusBtn = jQuery('#focus-filter-btn');
            var focusText = jQuery('#focus-filter-text');
            var test5Pass = (PipelineView.config.focusFilterActive && focusBtn.hasClass('active') && focusText.text() === 'Show All') || 
                           (!PipelineView.config.focusFilterActive && !focusBtn.hasClass('active') && focusText.text() === 'Show Focused');
            addTestResult('Test 5 - Focus Button State: ' + (test5Pass ? 'PASS' : 'FAIL'), test5Pass ? 'pass' : 'fail');
            
            // Test 6: Focus Filter LocalStorage
            var lsFocus = localStorage.getItem('pipeline_focus_filter');
            var test6Pass = lsFocus === (PipelineView.config.focusFilterActive ? 'true' : 'false');
            addTestResult('Test 6 - Focus LocalStorage: ' + (test6Pass ? 'PASS' : 'FAIL'), test6Pass ? 'pass' : 'fail');
            
            // Test 7: Focus Filter Visibility
            var visibleCards = jQuery('.deal-card:visible').length;
            var focusedCards = jQuery('.deal-card.focused-deal').length;
            var totalCards = jQuery('.deal-card').length;
            var test7Pass = (PipelineView.config.focusFilterActive && visibleCards === focusedCards) || 
                           (!PipelineView.config.focusFilterActive && visibleCards === totalCards);
            addTestResult('Test 7 - Card Visibility: ' + (test7Pass ? 'PASS' : 'FAIL'), test7Pass ? 'pass' : 'fail');
            
            updateStateDisplay();
        }
    </script>
</body>
</html>