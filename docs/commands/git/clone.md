# `tut git:clone`

**Clones the repo/branch to a specific location**

## Usage

```
# Clone repo.
tut git:clone --repo=the-events-calendar/event-tickets

# Clone repo but rename the cloned directory to "bacon/".
tut git:clone --repo=the-events-calendar/event-tickets --alias=bacon

# Clone repo in a specific parent directory.
tut git:clone --repo=the-events-calendar/event-tickets --path=/path/to/clone/to

# Clone repo shallowly with only one commit.
tut git:clone --repo=the-events-calendar/event-tickets --shallow-clone

# Clone repo, but only pull one branch
tut git:clone --repo=the-events-calendar/event-tickets --single-branch

# Maybe clone repo and prune branches that don't exist upstream.
tut git:clone --repo=the-events-calendar/event-tickets --prune

# Clone repo and checkout a specific ref (branch, commit, tag, etc).
tut git:clone --repo=the-events-calendar/event-tickets --ref=REF
```

## Args

```
Usage:
  git:clone [options]

Options:
      --repo=REPO       The name of the repo
      --path=PATH       Parent path to clone to
      --alias=ALIAS     Directory alias
      --shallow-clone   If included, will only do a shallow clone
      --single-branch   If included, will only clone a single branch
      --prune           If included, will prune non-upstream branches
      --ref=REF         The name of the ref (branch, commit, tag, etc)
  -h, --help            Display this help message

Help:
  Gets the latest commit on a branch
```
