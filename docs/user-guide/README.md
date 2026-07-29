# Repository-backed user guides

`registry.php` is the navigation, filter and evidence index consumed by
`App\Services\Documentation\DocumentationRegistry`. Article Markdown stays in
the matching guide directory.

For each article:

1. Add one registry entry with a unique `guide.slug` ID.
2. Declare audiences, brands (`all` is explicit), module, version introduced,
   last updated, owner, permissions, routes, related article IDs and source
   files.
3. Base operational statements on the declared routes, controllers and
   permission checks. A workspace label or hidden navigation item is not proof
   of server-side scope.
4. Include every required level-two section: Purpose, Intended users,
   Permissions, Fields, Actions, Workflows, Examples, Common mistakes, Related
   pages, FAQ, Version introduced, Last updated and Owner. Write “None on this
   page” where a section genuinely has no value.
5. Run `DocumentationRegistry::validate()` through
   `tests/Unit/DocumentationRegistryTest.php`.

The registry reads only declared files below this directory. Search results use
plain-text excerpts; HTML rendering is a separate escaped, allow-listed step.
