pipeline {
    agent any

    stages {
        stage('Install') {
            steps {
                sh 'cp -n .env.example .env || true'
                sh 'composer install --no-interaction --prefer-dist'
                sh 'php artisan key:generate --force'
            }
        }
        stage('Audit') {
            steps {
                // gagal jika ada kerentanan
                sh 'composer audit'
            }
        }
        stage('Build Frontend') {
            steps {
                sh 'npm install --ignore-scripts'
                sh 'npm run build'
            }
        }
        stage('Test + Coverage') {
            steps {
                sh '''
                    php -m | grep -i gd
                    vendor/bin/phpunit --coverage-clover coverage.xml --coverage-text=coverage.txt --log-junit junit.xml
                    php -r '
                      $x = simplexml_load_file("coverage.xml");
                      $m = $x->project->metrics;
                      $total = (int)$m["statements"] + (int)$m["methods"] + (int)$m["conditionals"];
                      $covered = (int)$m["coveredstatements"] + (int)$m["coveredmethods"] + (int)$m["coveredconditionals"];
                      $pct = $total ? $covered / $total * 100 : 100;
                      echo "Coverage: " . round($pct, 2) . "%\\n";
                      exit($pct < 100 ? 1 : 0);
                    '
                '''
            }
        }
        stage('Trivy') {
            steps {
                sh 'trivy fs --exit-code 0 --severity HIGH,CRITICAL . > trivy-report.txt || true'
            }
        }
        stage('Package') {
            steps {
                sh 'zip -r latest-app.zip . -x "vendor/*" "node_modules/*" ".git/*" ".env" "coverage*" "junit.xml" "trivy*"'
            }
        }
    }
}
