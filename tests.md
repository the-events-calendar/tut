# Tribe Product Utils Tests

## Set up
Run [Composer](https://getcomposer.org/) in the root folder:

```
composer install
```

create a local version of the PHPUnit configuration file:

```
cp ./phpunit.xml ./phpunit.xml
```

In that file update the `env` variables in the `<php></php>` section to match your local setup.
Then run the tests using `phpunit` from the root folder again

```
phpunit
```

and watch the magic happen.

## You break it, you buy it
Run the tests before touching the code: they should pass. Should that not be the case blame the person before you and ask him/her to fix the tests.
If the tests are passing then they should pass after you updated the code; should that not be the case resist the following temptations:

* ignore and commit nonetheless
* remove/delete/update the tests to avoid the failure
* remove the functionality you just added
* mark the test incomplete/skipped

Instead follow this counter-intuive approach and marvel at the power of your brain:

* write a test you are sure will pass
* run the test, if it passes write another test that should pass otherwise you just found the issue
* if all fails launch yourself into a cathartic long doc block where you explain why test X is failing and how really there is no way to avoid it; and as you write it you will found out you did not try everything and know how to fix stuff

Good (bug) hunting.
