import { FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Global teardown function that runs once after all tests
 */
async function globalTeardown(config: FullConfig) {
  console.log('\n🏁 MakeDealCRM E2E Test Suite Teardown...\n');

  // Calculate test run duration
  const startTime = global.__TEST_RUN_START_TIME__ || Date.now();
  const duration = Date.now() - startTime;
  const minutes = Math.floor(duration / 60000);
  const seconds = Math.floor((duration % 60000) / 1000);

  console.log(`⏱️  Total test run time: ${minutes}m ${seconds}s`);

  // Generate test summary
  const summaryPath = path.join(__dirname, '../reports/test-summary.json');
  const summary = {
    timestamp: new Date().toISOString(),
    duration: duration,
    baseUrl: process.env.BASE_URL || config.use?.baseURL,
    workers: config.workers,
    projects: config.projects.map(p => p.name)
  };

  try {
    fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
    console.log('📊 Test summary written to reports/test-summary.json');
  } catch (error) {
    console.error('Failed to write test summary:', error);
  }

  // Clean up old test artifacts (older than 7 days)
  const artifactDirs = ['test-results', 'reports/screenshots', 'reports/videos', 'reports/traces'];
  const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);

  artifactDirs.forEach(dir => {
    const fullPath = path.join(__dirname, '..', dir);
    if (fs.existsSync(fullPath)) {
      try {
        const files = fs.readdirSync(fullPath);
        let cleaned = 0;
        
        files.forEach(file => {
          const filePath = path.join(fullPath, file);
          const stats = fs.statSync(filePath);
          
          if (stats.isFile() && stats.mtimeMs < sevenDaysAgo) {
            fs.unlinkSync(filePath);
            cleaned++;
          }
        });

        if (cleaned > 0) {
          console.log(`🧹 Cleaned ${cleaned} old files from ${dir}`);
        }
      } catch (error) {
        console.error(`Failed to clean ${dir}:`, error.message);
      }
    }
  });

  console.log('\n✅ Global teardown completed\n');
}

export default globalTeardown;