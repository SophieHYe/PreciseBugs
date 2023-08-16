# About

Github Action to check the specified string, e.g. Github PR title, for
work-in-progress ("WIP") patterns using regular expressions in `BASH`.

# Usage

## Simple with Defaults

```yaml
name: Check "WIP" in PR Title

on:
  pull_request:
    types: [opened, synchronize, reopened, edited]

jobs:
  check:
    runs-on: ubuntu-latest
    steps:
      - name: Check WIP in PR Title
        uses: embano1/wip@v1
```

## Custom Options

```yaml
name: Check "WIP" in PR Title

on:
  pull_request:
    types: [opened, synchronize, reopened, edited]

jobs:
  check:
    runs-on: ubuntu-latest
    steps:
      - name: Check WIP in PR Title
        uses: embano1/wip@v1
        with:
          # this is also the default value
          title: "${{ github.event.pull_request.title }}"

          # only matches PRs where title is "WIP" only
          regex: "^WIP$" 

```

## Configuration Options

| Input   | Type     | Required | Default                                  | Description                                                                                                                                                                   |
|---------|----------|----------|------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `title` | `string` | yes      | `${{ github.event.pull_request.title }}` | The title to perform regular expression pattern matching against. Typically a field from the Github [`context`](https://docs.github.com/en/actions/reference/context-and-expression-syntax-for-github-actions#github-context) is used.                                                                                                              |
| `regex` | `string` | yes      | `^[[:space:]]*(WIP)+(:)*`                | The regular expression to perform. The default value matches the word `WIP` (optionally followed by `:`) and ignores any whitespace character(s) at the beginning of the text |

⚠️ Currently regular expression pattern matching is **case-insensitive**, i.e.
`wip` would also match.