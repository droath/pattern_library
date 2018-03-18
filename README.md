## Pattern Library

The pattern library module is intended to be used to connect patterns defined in a third-party library like Pattern Labs or Partial to Drupal. Each pattern that is defined will build a layout, which can be used in either display suite, page manager, or any module that utilizes the Layouts API.

Most patterns are not simple, and rely on modifiers on which can be set by the site builder or content administrator. This is where the power of pattern library comes in. As when defining patterns (via theme, or module) you can also expose modifiers on which to collect input from the site-builder.

Modifier types are exposed using the Plugin API, so other modules can provide their own modifiers if needed.

