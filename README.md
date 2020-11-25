# OS2Web Meetings Agenda Management Drupal module [![Build Status](https://travis-ci.org/OS2web/os2web_meetings_agman.svg?branch=master)](https://travis-ci.org/OS2web/os2web_meetings_agman)

## Module purpose

The aim of this module is to import meetings from Agenda Management ESDH providers.

This module is highly dependent on  [Os2web Meetings](https://github.com/OS2web/os2web_meetings) module and serves as an implementation of it.

## How does it work

Meetings are being imported from so called agenda or manifest files, that are provided by specific Agenda Management ESDH provider.

OS2Web Meetings Agenda Management serves as an adapter that converts meetings into a canonical format.

After "translating" each meeting is then processed and eventually imported in the system.

Import is handled via Migrate API, which is part of the Drupal 8 core functionality.

## Additional settings
Settings are available under ```admin/config/system/os2web-meetings```
* **Agenda Management Meetings manifests path** - Path to agenda directory.

## Install

Module is available to download via composer.
```
composer require os2web/os2web_meetings_agman
drush en os2web_meetings_agman
```

## Import process

The import process can be done in two ways:
* Via Drush (recommended)
    * Use the following Drush command to start the migration:
        ```
        drush migrate:import os2web_meetings_agman_import
        ```
        Read more about the Drush commands for Migrate API on [Migrate tools](https://www.drupal.org/project/migrate_tool).
    * It is highly recommended to set up a cronjob on your server to do the run this command often

* Via Admin UI
    * Go to ```admin/structure/migrate/manage/os2web_meetings/migrations``` on your installation
    * Click ```Execute``` next to **Meeting import (Agenda Management)**
    * Click ```Execute``` on the next page as well (doing that will use default options).

## Update
Updating process for OS2Web Meetings module is similar to usual Drupal 8 module.
Use Composer's built-in command for listing packages that have updates available:

```
composer outdated os2web/os2web_meetings_agman
```

## Automated testing and code quality
See [OS2Web testing and CI information](https://github.com/OS2Web/docs#testing-and-ci)

## Contribution

Project is opened for new features and os course bugfixes.
If you have any suggestion or you found a bug in project, you are very welcome
to create an issue in github repository issue tracker.
For issue description there is expected that you will provide clear and
sufficient information about your feature request or bug report.

### Code review policy
See [OS2Web code review policy](https://github.com/OS2Web/docs#code-review)

### Git name convention
See [OS2Web git name convention](https://github.com/OS2Web/docs#git-guideline)
