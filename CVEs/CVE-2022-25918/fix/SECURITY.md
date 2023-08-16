# Security Policy

## Supported Versions

The table below shows which versions of _Shescape_ are currently supported with
security updates.

| Version | Supported | End-of-life |
| ------: | :-------- | :---------- |
|   1.x.x | Yes       | -           |
|   0.x.x | No        | 2021-02-01  |

## Reporting a Vulnerability

The maintainers of this project take security bugs very seriously. We appreciate
your efforts to responsibly disclose your findings. Due to the non-funded and
open-source nature of this project, we take a best-efforts approach when it
comes to engaging with (security) reports.

To report a security issue in a supported version of the project, send an email
to [security@ericcornelissen.dev] and include the terms "SECURITY" and
"shescape" in the subject line. Please do not open a regular issue or Pull
Request in the public repository.

If you found a security bug in an unsupported version of the project, please
report this publicly. For example, as a regular issue in the public repository.

## Advisories

| ID                    | Date       | Affected versions | Patched versions |
| :-------------------- | :--------- | :---------------- | :--------------- |
| `CVE-2021-21384`      | 2021-03-19 | `<1.1.3`          | `1.1.3`          |
| `CVE-2022-24725`      | 2022-03-03 | `>=1.4.0 <1.5.1`  | `1.5.1`          |
| `CVE-2022-31179`      | 2022-07-26 | `<1.5.8`          | `1.5.8`          |
| `CVE-2022-31180`      | 2022-07-26 | `>=1.4.0 <1.5.8`  | `1.5.8`          |
| `CVE-2022-36064`      | 2022-08-29 | `>=1.5.1 <1.5.10` | `1.5.10`         |
| `GHSA-cr84-xvw4-qx3c` | 2022-10-25 | `>=1.5.10 <1.6.1` | `1.6.1`          |

## Acknowledgments

We would like to publicly thank the following reporters:

- Elliot Ward ([@mowzk]) from [Snyk]

[@mowzk]: https://github.com/mowzk
[security@ericcornelissen.dev]: mailto:security@ericcornelissen.dev?subject=SECURITY%20%28shescape%29
[snyk]: https://snyk.io/
