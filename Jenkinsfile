elifePipeline({
    def civiSiteKey
    def civiApiKey

    node('containers-jenkins-plugin') {
        stage 'Checkout', {
            checkout scm
        }
        stage 'install composer dependencies', {
            sh "docker build . -t civi-contacts:latest"
        }
        stage 'get civi credentials', {
            civiSiteKey = sh(script: 'vault.sh kv get -field site-key secret/containers/civi-contacts', returnStdout: true).trim()
            civiApiKey = sh(script: 'vault.sh kv get -field api-key secret/containers/civi-contacts', returnStdout: true).trim()
        }
        stage 'Run topup script', {
            sh "docker run --rm -i -e CIVI_SITE_KEY=${civiSiteKey} -e CIVI_API_KEY=${civiApiKey} civi-contacts:latest"
        }
    }
})
