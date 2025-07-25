const fs = require('fs');
const path = require('path');

console.log('🔍 Verifying Code Changes\n');

let passedTests = 0;
let failedTests = 0;

function logTest(name, testPassed, details = '') {
    if (testPassed) {
        console.log(`✅ ${name}`);
        passedTests++;
    } else {
        console.log(`❌ ${name}`);
        if (details) console.log(`   ${details}`);
        failedTests++;
    }
}

// 1. Check if PipelineKanbanView.js has the window.location.href changes
console.log('1️⃣ Checking PipelineKanbanView.js changes...');
const pipelineJsPath = path.join(__dirname, 'custom/modules/Pipelines/views/PipelineKanbanView.js');
try {
    const content = fs.readFileSync(pipelineJsPath, 'utf8');
    
    // Check for window.location.href
    const hasLocationHref = content.includes("window.location.href='index.php?module=");
    logTest('Uses window.location.href for navigation', hasLocationHref);
    
    // Check that window.open is not used
    const hasWindowOpen = content.includes("window.open('index.php?module=") && 
                         !content.includes("// window.open");
    logTest('Removed window.open calls', !hasWindowOpen);
    
    // Check for commented alerts
    const hasCommentedSuccessMsg = content.includes('// this.showSuccessMessage');
    const hasCommentedErrorMsg = content.includes('// this.showErrorMessage');
    const hasCommentedLoading = content.includes('// this.showLoadingIndicator');
    
    logTest('Success messages are commented out', hasCommentedSuccessMsg);
    logTest('Error messages are commented out', hasCommentedErrorMsg);
    logTest('Loading indicators are commented out', hasCommentedLoading);
    
    // Show actual onclick handlers
    const viewMatch = content.match(/onclick="([^"]*view-deal[^"]*)"/);
    const editMatch = content.match(/onclick="([^"]*edit-deal[^"]*)"/);
    
    if (viewMatch) {
        console.log(`\n   View button onclick: ${viewMatch[1]}`);
    }
    if (editMatch) {
        console.log(`   Edit button onclick: ${editMatch[1]}`);
    }
    
} catch (error) {
    logTest('PipelineKanbanView.js exists', false, error.message);
}

// 2. Check if controller exists
console.log('\n2️⃣ Checking Deals controller...');
const controllerPath = path.join(__dirname, 'custom/modules/Deals/controller.php');
try {
    const content = fs.readFileSync(controllerPath, 'utf8');
    const hasPipelineAction = content.includes('action_pipeline');
    logTest('Deals controller has pipeline action', hasPipelineAction);
} catch (error) {
    logTest('Deals controller exists', false, error.message);
}

// 3. Check if pipeline view exists
console.log('\n3️⃣ Checking pipeline view...');
const viewPath = path.join(__dirname, 'custom/modules/Deals/views/view.pipeline.php');
try {
    const content = fs.readFileSync(viewPath, 'utf8');
    const hasClass = content.includes('class DealsViewPipeline');
    const hasInit = content.includes('new PipelineKanbanView');
    logTest('Pipeline view exists', true);
    logTest('Pipeline view has correct class name', hasClass);
    logTest('Pipeline view initializes JavaScript', hasInit);
} catch (error) {
    logTest('Pipeline view exists', false, error.message);
}

// Summary
console.log('\n📊 VERIFICATION SUMMARY');
console.log('='.repeat(30));
console.log(`✅ Passed: ${passedTests}`);
console.log(`❌ Failed: ${failedTests}`);
console.log(`Total: ${passedTests + failedTests}`);

if (failedTests === 0) {
    console.log('\n🎉 All code changes are verified!');
    console.log('\nThe following changes have been successfully implemented:');
    console.log('1. Deal cards now use window.location.href (same window navigation)');
    console.log('2. Alert messages during drag/drop have been commented out');
    console.log('3. Pipeline view infrastructure is in place');
} else {
    console.log('\n⚠️ Some verifications failed. Check the details above.');
}