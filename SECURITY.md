# Security Policy

## Supported Versions

Active development of Grav happens on the **develop** branch. All security work lands on 2.0 first, and 2.0 is the recommended target for any new install.

| Version | Status                           | Notes                                                                                                                                  |
| ------- | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 2.0.x   | :white_check_mark: Active        | Current development line, shipping as stable. All security fixes land here.                                                                |
| 1.7.x   | :warning: Limited maintenance    | Only critical issues exploitable **without** admin or publisher access get backported. See [What gets backported to 1.7](#what-gets-backported-to-17) below. |
| 1.8.x   | :x: Not supported                | 1.8 was only ever a beta line. It has been replaced wholesale by 2.0 RC. No further releases or backports.                             |
| < 1.7   | :x: Not supported                |                                                                                                                                        |

## :pushpin: Note on Security Severity

The Grav project rates security issues by **whether the issue crosses a trust boundary**, not by which account level can trigger it. A publisher running Twig in pages they author is doing exactly what publishers are entrusted to do. An admin running CLI tools or editing config is doing exactly what admins are entrusted to do. Those capabilities are not vulnerabilities, they are the role.

A vulnerability is when an actor can **escape the trust scope of their role**: a publisher whose stored content compromises an admin session, an unauthenticated visitor who reaches a privileged sink, an account at any tier that gains capabilities it was not granted.

Please use the following guidelines when selecting a **Severity** in a GitHub Security Advisory. Reports submitted at **High** or **Critical** that do not meet these guidelines will be re-classified or closed.

| Severity     | When to use                                                                                                                                                                                                                                                                                          |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CRITICAL** | An **unauthenticated** attacker can achieve RCE, exfiltrate site data, or gain admin-equivalent control. No Grav account required.                                                                                                                                                                   |
| **HIGH**     | A **cross-trust-boundary** issue. A lower-privilege actor (or anonymous visitor against a stored payload) ends up running code, exfiltrating data, or taking actions inside a higher-privilege session. Examples: stored XSS that fires in a super-admin session, publisher-to-admin privilege escalation, CSRF that elevates privileges. |
| **MODERATE** | An authenticated user can do something **outside the documented scope of their role**, but the impact stays within their own session or affects only same-tier users.                                                                                                                                |
| **LOW**      | An admin or super-admin can do something nefarious **within their already-granted capabilities**. In practice these are usually **wontfix / by design**, because giving someone admin keys means trusting them with admin keys.                                                                      |

The CVSS score that the GitHub advisory form computes does **not** override these guidelines. CVSS rewards impact regardless of trust scope, which inflates ratings for issues that are actually in-scope behaviour for the role that triggers them. We will downgrade or close on the criteria above.
