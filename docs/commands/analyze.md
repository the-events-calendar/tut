# `analyze`

**Changelog entries for Hooks and Template changes**

On the end of the release the Developer responsible for the release will run this command to add the automated changelog entries for our users.

## Usage

```
$ tut analyze

event-tickets
-------------

 * Tweak - Changed views: `blocks/rsvp`, `blocks/rsvp/content-inactive`, `blocks/rsvp/content`, `blocks/rsvp/form`, `blocks/rsvp/form/form`, `blocks/rsvp/form/submit-login`, `blocks/rsvp/form/submit`, `blocks/rsvp/status`, `blocks/rsvp/status/going-icon`, `blocks/rsvp/status/going`, `blocks/rsvp/status/not-going`, `blocks/tickets`, `blocks/tickets/content-inactive`, `blocks/tickets/content`, `blocks/tickets/item-inactive`, `blocks/tickets/item`, `registration/button-checkout`
```

## Args

```
Usage:
  analyze [options]

Options:
      --dry-run            Whether the command should really execute or not.
  -p, --plugin=PLUGIN      A comma separated list of plugins that will be pushed (multiple values allowed)
  -o, --output[=OUTPUT]    Which type of output we are looking for [default: "changelog"]
  -c, --compare[=COMPARE]  To which commit we are comparing to.
  -m, --memory[=MEMORY]    When comparing with old Commits, make sure to bump the total memory, use MB.
  -h, --help               Display this help message

Help:
  This will analyze changes made on plugins
```
