# `tut generate-css-override`

**Finds CSS rules for the purposes of providing overrides.**

Looks through CSS files to find rules that contain the provided search value and generates an override rule with the provided replace value.

## Usage

```bash
tut generate-css-override --search='"Helvetica Neue", Helvetica, -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif' --replace="font-family: inherit;"
```

## Args

```
Usage:
  generate-css-override [options]

Options:
      --dry-run          Whether the command should really execute or not.
  -p, --plugin=PLUGIN    A comma separated list of plugins that will be pushed (multiple values allowed)
      --search=SEARCH    
      --replace=REPLACE  
  -h, --help             Display this help message

Help:
  This command generates CSS with a specific property override
```
