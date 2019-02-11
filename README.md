# shifter

Push-To-Github and Run LaravelShift.com Helper Script

# Background

LaravelShift.com is a great service. Unfortunately it does not allow to connect directly to own GitLab instances.

Repositories must be on GitHub, BitBucket or public GitLab.

# What it does

Push your repository to a on-the-fly created private GitHub repository. Instruct you to run LaravelShift.com.

# Alternatives

You can also use dockerized shifts.

# Installation

1. Clone this repository
2. Obtain a GitHub token and store it in `.github_token`
3. Run composer install
4. Symlink shifter.php  
    
    sudo ln -s `realpath shifter.php` /usr/local/bin/shifter


For each project you want to shift

* Call `shifter` from the directory of your project
