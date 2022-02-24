elifePipeline({
    stage "install composer dependencies" {
        sh "composer install"
    }
    stage 'Run topup script', {
        sh "./console subscriber:urls"
    }
})
