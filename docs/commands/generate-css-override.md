# `tut generate-css-override`

**Finds CSS rules for the purposes of providing overrides.**

Looks through CSS files to find rules that contain the provided search value and generates an override rule with the provided replace value.

## Example

```bash
tut generate-css-override --search='"Helvetica Neue", Helvetica, -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif' --replace="font-family: inherit;"
```
