## `tut svn:release`

**Release new versions on WP.org**

When dealing with WordPress.org SVN release it's time consuming to do a checksum of the tag you just created, this command aims to simplify the process by doing it for you before releasing.

## Usage
```
  tut svn:release [options] [--] <plugin> <tag>
```

### Arguments:
```
  plugin                             The slug of the Plugin on WordPress.org.
  tag                                The SVN tag to be released.
```

### Options:

```
  -c, --checksum_zip[=CHECKSUM_ZIP]  The URL of the ZIP file in case you want to do a Checksum.
  -t, --temp_dir[=TEMP_DIR]          The temporary directory to use. [default: "/tmp/svn-tag/"]
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
  This command allows you to update what is the Stable Tag version of a WP.org plugin.
 ```
