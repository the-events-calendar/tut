## `tut svn:tag`

**Tagging new versions on WP.org**

When dealing with WordPress.org SVN release it can be extremely time consuming to do a fresh new tag of a large plugin since all files get scanned by the their bots. This command aims to simplify the process by copying an existing tag and applying the changes from a ZIP file.

## Usage
```
  tut svn:tag [options] [--] <plugin> <source_tag> <destination_tag>
```

### Arguments:
```
  plugin                             The slug of the Plugin on WordPress.org.
  source_tag                         The SVN tag to be copied.
  destination_tag                    The new SVN tag.
```

### Options:

```
  -z, --zip_url[=ZIP_URL]            The URL of the ZIP file with changes to apply.
  -t, --temp_dir[=TEMP_DIR]          The temporary directory to use. [default: "/tmp/svn-tag/"]
  -m, --memory_limit[=MEMORY_LIMIT]  How much memory we clear for usage, since some of the operations can be expensive. [default: "512M"]
  -h, --help                         Display this help message
  -q, --quiet                        Do not output any message
  -V, --version                      Display this application version
      --ansi                         Force ANSI output
      --no-ansi                      Disable ANSI output
  -n, --no-interaction               Do not ask any interactive question
  -v|vv|vvv, --verbose               Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### Help:
```
  This command allows you to create a new SVN tag from an existing tag and apply changes from a ZIP file.
```