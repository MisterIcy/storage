## What does this PR do?

<!-- A high-level summary of the change. One paragraph is enough. -->

## Why do we need it?

<!-- Why is this change necessary? Link to the problem it solves, the user
     pain it addresses, or the design decision that motivates it. -->

## Related issues or PRs

<!-- Reference related issues or PRs using GitHub keywords where applicable.
     Examples:
       Closes #123
       Relates to #456
       Supersedes #789
-->

## Breaking changes

<!-- List any backwards-incompatible changes introduced by this PR.
     If there are none, write "None".
     Examples: removed method, changed method signature, renamed class,
     changed exception type. -->

---

## Contributor checklist

Before requesting a review, confirm every item below:

- [ ] All new and existing **tests pass** locally (`./vendor/bin/phpunit`)
- [ ] **PHPStan** reports no errors (`./vendor/bin/phpstan analyse`)
- [ ] **PHP CS Fixer** reports no violations (`./vendor/bin/php-cs-fixer fix --dry-run --diff`)
- [ ] **Infection** mutation score is at or above the baseline (`./vendor/bin/infection`)
- [ ] The **CI pipeline is green** on this branch
- [ ] Any **security implications** of this change are described above, or explicitly stated as "None"
- [ ] Any **performance implications** of this change are described above, or explicitly stated as "None"
- [ ] This PR targets the correct base branch (`main`)

<!-- Work in progress? Open this as a Draft PR instead. -->
