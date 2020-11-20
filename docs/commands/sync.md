# `tut sync`

**Git branch synchronization**

If you wish to globally check out a specific branch across all of the Tribe plugins in a directory.

## Usage

```sh
# Pulls Current Release
tut sync master

# Pulls Maintenance Release
tut sync release/B20.01

tut sync release/B20.01 -p tec,pro

tut sync release/B20.01 --plugin tec --plugin pro

// Pushes Current Release
tut sync release/B20.01 -d up
```

