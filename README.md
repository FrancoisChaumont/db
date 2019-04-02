# DB - Database management library using PDO

![GitHub release](https://img.shields.io/github/release/FrancoisChaumont/db.svg)
[![contributions welcome](https://img.shields.io/badge/contributions-welcome-brightgreen.svg?style=flat)](https://github.com/FrancoisChaumont/db/issues)
[![GitHub issues](https://img.shields.io/github/issues/FrancoisChaumont/db.svg)](https://github.com/FrancoisChaumont/db/issues)
[![GitHub stars](https://img.shields.io/github/stars/FrancoisChaumont/db.svg)](https://github.com/FrancoisChaumont/db/stargazers)
![Github All Releases](https://img.shields.io/github/downloads/FrancoisChaumont/db/total.svg)

PHP libray to manage databases using PDO  

**Currently supports:**  
`MySQL, MariaDB, PostgreSQL` - tested  
`possibly others` - to be tested

## Getting started
These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Requirements
PHP 7.1+

### Installation
Install this package with composer by simply adding the following to your composer.json file:  
```
"repositories": [
    {
        "url": "https://github.com/FrancoisChaumont/db.git",
        "type": "git"
    }
]
```
and running the following command:  
```
composer require francoischaumont/db "^3.0"
```

## Testing
Under the folder named *tests* you will find a SQL file and a test script ready to use.
The SQL file is a dump of a test database which the test script relies on.

## Built with
* Visual Studio Code

## Authors
* **Francois Chaumont** - *Initial work* - [FrancoisChaumont](https://github.com/FrancoisChaumont)

See also the list of [contributors](https://github.com/FrancoisChaumont/db/graphs/contributors) who particpated in this project.

## License
This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Notes
Todo: Test support to more databases

