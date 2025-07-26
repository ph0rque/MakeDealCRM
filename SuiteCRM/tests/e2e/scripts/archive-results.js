const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);

class ResultsArchiver {
  constructor() {
    this.resultsDir = path.resolve(__dirname, '../test-results');
    this.archiveDir = path.resolve(__dirname, '../test-archives');
    this.retentionDays = parseInt(process.env.ARCHIVE_RETENTION_DAYS) || 30;
    this.compressionLevel = process.env.COMPRESSION_LEVEL || '6'; // 1-9, higher = better compression
    this.maxArchiveSize = parseInt(process.env.MAX_ARCHIVE_SIZE_MB) || 100; // MB
  }

  async archive() {
    console.log('Starting test results archival...');

    try {
      // Ensure archive directory exists
      if (!fs.existsSync(this.archiveDir)) {
        fs.mkdirSync(this.archiveDir, { recursive: true });
      }

      // Create archive metadata
      const archiveInfo = await this.createArchiveInfo();
      
      // Create archive
      const archivePath = await this.createArchive(archiveInfo);
      
      // Validate archive
      await this.validateArchive(archivePath);
      
      // Clean old archives
      await this.cleanOldArchives();
      
      // Generate archive index
      await this.generateArchiveIndex();
      
      console.log(`Archive created successfully: ${archivePath}`);
      
    } catch (error) {
      console.error('Archival failed:', error.message);
      process.exit(1);
    }
  }

  async createArchiveInfo() {
    const timestamp = new Date().toISOString();
    const buildNumber = process.env.BUILD_NUMBER || 'local';
    const gitCommit = process.env.GIT_COMMIT || '';
    const gitBranch = process.env.GIT_BRANCH || 'unknown';

    // Load test results summary if available
    let testSummary = null;
    const summaryPath = path.join(this.resultsDir, 'consolidated-report.json');
    if (fs.existsSync(summaryPath)) {
      try {
        const data = JSON.parse(fs.readFileSync(summaryPath, 'utf8'));
        testSummary = {
          total: data.summary.total,
          passed: data.summary.passed,
          failed: data.summary.failed,
          duration: data.summary.duration,
          passRate: ((data.summary.passed / data.summary.total) * 100).toFixed(1)
        };
      } catch (e) {
        console.warn('Could not load test summary for archive metadata');
      }
    }

    return {
      timestamp,
      buildNumber,
      gitCommit,
      gitBranch,
      testSummary,
      archiveId: `${timestamp.split('T')[0]}-build-${buildNumber}`,
      created: new Date().toISOString()
    };
  }

  async createArchive(archiveInfo) {
    const archiveFilename = `test-results-${archiveInfo.archiveId}.tar.gz`;
    const archivePath = path.join(this.archiveDir, archiveFilename);

    console.log(`Creating archive: ${archiveFilename}`);

    // Create metadata file
    const metadataPath = path.join(this.resultsDir, 'archive-metadata.json');
    fs.writeFileSync(metadataPath, JSON.stringify(archiveInfo, null, 2));

    try {
      // Create compressed archive
      const tarCommand = `tar -czf "${archivePath}" -C "${path.dirname(this.resultsDir)}" "${path.basename(this.resultsDir)}"`;
      
      console.log('Compressing test results...');
      const { stdout, stderr } = await execAsync(tarCommand);
      
      if (stderr && !stderr.includes('tar:')) {
        console.warn('Compression warnings:', stderr);
      }

      // Check archive size
      const stats = fs.statSync(archivePath);
      const sizeMB = (stats.size / 1024 / 1024).toFixed(2);
      
      console.log(`Archive size: ${sizeMB} MB`);
      
      if (stats.size > this.maxArchiveSize * 1024 * 1024) {
        console.warn(`Archive size (${sizeMB} MB) exceeds maximum (${this.maxArchiveSize} MB)`);
      }

      // Update archive info with actual file stats
      archiveInfo.archive = {
        filename: archiveFilename,
        path: archivePath,
        size: stats.size,
        sizeMB: parseFloat(sizeMB),
        created: stats.birthtime.toISOString()
      };

      // Save updated metadata
      fs.writeFileSync(metadataPath, JSON.stringify(archiveInfo, null, 2));

      return archivePath;

    } catch (error) {
      // Clean up partial archive on failure
      if (fs.existsSync(archivePath)) {
        fs.unlinkSync(archivePath);
      }
      throw new Error(`Archive creation failed: ${error.message}`);
    }
  }

  async validateArchive(archivePath) {
    console.log('Validating archive integrity...');

    try {
      // Test archive integrity
      const testCommand = `tar -tzf "${archivePath}" > /dev/null`;
      await execAsync(testCommand);
      
      // List contents to verify structure
      const listCommand = `tar -tzf "${archivePath}" | head -20`;
      const { stdout } = await execAsync(listCommand);
      
      console.log('Archive contents (first 20 files):');
      console.log(stdout);
      
      // Verify key files are present
      const requiredFiles = [
        'test-results/archive-metadata.json'
      ];
      
      const verifyCommand = `tar -tzf "${archivePath}" | grep -E "(${requiredFiles.join('|')})"`;
      const { stdout: foundFiles } = await execAsync(verifyCommand);
      
      if (!foundFiles.trim()) {
        throw new Error('Archive missing required files');
      }
      
      console.log('Archive validation successful');
      
    } catch (error) {
      throw new Error(`Archive validation failed: ${error.message}`);
    }
  }

  async cleanOldArchives() {
    console.log('Cleaning old archives...');

    try {
      const files = fs.readdirSync(this.archiveDir)
        .filter(file => file.endsWith('.tar.gz'))
        .map(file => {
          const filePath = path.join(this.archiveDir, file);
          const stats = fs.statSync(filePath);
          return {
            name: file,
            path: filePath,
            created: stats.birthtime,
            size: stats.size
          };
        })
        .sort((a, b) => b.created - a.created); // Newest first

      const cutoffDate = new Date();
      cutoffDate.setDate(cutoffDate.getDate() - this.retentionDays);

      let deletedCount = 0;
      let reclaimedBytes = 0;

      for (const file of files) {
        if (file.created < cutoffDate) {
          console.log(`Deleting old archive: ${file.name} (${file.created.toDateString()})`);
          fs.unlinkSync(file.path);
          deletedCount++;
          reclaimedBytes += file.size;
          
          // Also delete corresponding metadata if exists
          const metadataPath = file.path.replace('.tar.gz', '-metadata.json');
          if (fs.existsSync(metadataPath)) {
            fs.unlinkSync(metadataPath);
          }
        }
      }

      if (deletedCount > 0) {
        const reclaimedMB = (reclaimedBytes / 1024 / 1024).toFixed(2);
        console.log(`Cleaned ${deletedCount} old archives, reclaimed ${reclaimedMB} MB`);
      } else {
        console.log('No old archives to clean');
      }

    } catch (error) {
      console.warn('Archive cleanup failed:', error.message);
    }
  }

  async generateArchiveIndex() {
    console.log('Generating archive index...');

    try {
      const files = fs.readdirSync(this.archiveDir)
        .filter(file => file.endsWith('.tar.gz'))
        .map(file => {
          const filePath = path.join(this.archiveDir, file);
          const stats = fs.statSync(filePath);
          
          // Try to load metadata
          let metadata = null;
          const metadataPath = path.join(this.resultsDir, 'archive-metadata.json');
          if (fs.existsSync(metadataPath)) {
            try {
              metadata = JSON.parse(fs.readFileSync(metadataPath, 'utf8'));
            } catch (e) {
              // Ignore metadata loading errors
            }
          }

          return {
            filename: file,
            size: stats.size,
            sizeMB: (stats.size / 1024 / 1024).toFixed(2),
            created: stats.birthtime.toISOString(),
            metadata: metadata
          };
        })
        .sort((a, b) => new Date(b.created) - new Date(a.created));

      const index = {
        generated: new Date().toISOString(),
        totalArchives: files.length,
        totalSize: files.reduce((sum, file) => sum + file.size, 0),
        retentionDays: this.retentionDays,
        archives: files
      };

      const indexPath = path.join(this.archiveDir, 'index.json');
      fs.writeFileSync(indexPath, JSON.stringify(index, null, 2));

      // Generate HTML index
      const htmlIndex = this.generateHtmlIndex(index);
      const htmlPath = path.join(this.archiveDir, 'index.html');
      fs.writeFileSync(htmlPath, htmlIndex);

      console.log(`Archive index generated: ${indexPath}`);

    } catch (error) {
      console.warn('Index generation failed:', error.message);
    }
  }

  generateHtmlIndex(index) {
    const totalSizeMB = (index.totalSize / 1024 / 1024).toFixed(2);

    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results Archive Index</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .header { background: #34495e; color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
        .summary { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .archives { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .download-link { color: #007bff; text-decoration: none; }
        .download-link:hover { text-decoration: underline; }
        .test-summary { font-size: 12px; color: #6c757d; }
        .pass-rate { font-weight: bold; }
        .pass-rate.good { color: #28a745; }
        .pass-rate.warning { color: #ffc107; }
        .pass-rate.danger { color: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Test Results Archive Index</h1>
        <p>Archive management for MakeDealCRM E2E test results</p>
    </div>

    <div class="summary">
        <h3>Archive Summary</h3>
        <p><strong>Total Archives:</strong> ${index.totalArchives}</p>
        <p><strong>Total Size:</strong> ${totalSizeMB} MB</p>
        <p><strong>Retention Policy:</strong> ${index.retentionDays} days</p>
        <p><strong>Last Updated:</strong> ${new Date(index.generated).toLocaleString()}</p>
    </div>

    <div class="archives">
        <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Available Archives</h3>
        <table>
            <thead>
                <tr>
                    <th>Archive</th>
                    <th>Date</th>
                    <th>Build</th>
                    <th>Branch</th>
                    <th>Test Results</th>
                    <th>Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${index.archives.map(archive => {
                  const date = new Date(archive.created).toLocaleDateString();
                  const time = new Date(archive.created).toLocaleTimeString();
                  const buildNumber = archive.metadata?.buildNumber || 'unknown';
                  const branch = archive.metadata?.gitBranch || 'unknown';
                  
                  let testSummary = 'No data';
                  let passRateClass = '';
                  
                  if (archive.metadata?.testSummary) {
                    const s = archive.metadata.testSummary;
                    const passRate = parseFloat(s.passRate);
                    passRateClass = passRate >= 95 ? 'good' : passRate >= 80 ? 'warning' : 'danger';
                    testSummary = `${s.passed}/${s.total} tests (${s.passRate}%)`;
                  }

                  return `
                    <tr>
                        <td>
                            <a href="${archive.filename}" class="download-link">${archive.filename}</a>
                        </td>
                        <td>
                            ${date}<br>
                            <small>${time}</small>
                        </td>
                        <td>#${buildNumber}</td>
                        <td>${branch}</td>
                        <td>
                            <span class="test-summary pass-rate ${passRateClass}">${testSummary}</span>
                        </td>
                        <td>${archive.sizeMB} MB</td>
                        <td>
                            <a href="${archive.filename}" class="download-link">Download</a>
                        </td>
                    </tr>
                  `;
                }).join('')}
            </tbody>
        </table>
        ${index.archives.length === 0 ? '<p style="padding: 20px; text-align: center; color: #6c757d;">No archives available</p>' : ''}
    </div>

    <div style="margin-top: 30px; text-align: center; color: #6c757d; font-size: 12px;">
        Generated on ${new Date(index.generated).toLocaleString()}
    </div>
</body>
</html>`;
  }

  async extractArchive(archivePath, extractPath) {
    console.log(`Extracting archive to: ${extractPath}`);

    try {
      // Ensure extract directory exists
      if (!fs.existsSync(extractPath)) {
        fs.mkdirSync(extractPath, { recursive: true });
      }

      // Extract archive
      const extractCommand = `tar -xzf "${archivePath}" -C "${extractPath}"`;
      await execAsync(extractCommand);

      console.log('Archive extracted successfully');

    } catch (error) {
      throw new Error(`Archive extraction failed: ${error.message}`);
    }
  }
}

// Run if called directly
if (require.main === module) {
  const archiver = new ResultsArchiver();
  
  // Handle command line arguments
  const args = process.argv.slice(2);
  const command = args[0];

  if (command === 'extract' && args[1] && args[2]) {
    // Extract archive: node archive-results.js extract <archive-path> <extract-path>
    archiver.extractArchive(args[1], args[2]).catch(error => {
      console.error('Extraction failed:', error);
      process.exit(1);
    });
  } else {
    // Default: create archive
    archiver.archive().catch(error => {
      console.error('Archival failed:', error);
      process.exit(1);
    });
  }
}

module.exports = ResultsArchiver;