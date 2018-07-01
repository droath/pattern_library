# Pattern Library


The pattern library module provides a way to define patterns that are managed by an internal or external pattern framework like [Pattern Lab](http://patternlab.io/) to the Drupal ecosystem. This is as easy as placing a pattern definition in either a custom module or theme. 

## <a name="integrations"></a> Integrations

#### Entity Display

The pattern library module doesn't ship with a way to render patterns per se, as it's only responsibility is to generate layouts. The [layouts API](https://www.drupal.org/docs/8/api/layout-api) was introduced in Drupal 8.3. There are many contributed modules that use layouts to render content, such as [panels](https://www.drupal.org/project/panels), [display suite](https://www.drupal.org/project/ds) or layout builder which is now apart of Drupal core, as of 8.5.

#### Field Formatter

Most field types will have an additional field formatter, labeled **Pattern Library**. This formatter allows for mapping field properties to a layout pattern. A common use case would be having a link field, which has two properties, URI and text. These properties could easily be mapped to a button pattern. 

There is another field formatter that's available for entity reference field types, which is labeled **Entity Properties**. This allow for a site-builder to access properties from an entity reference. As most things in Drupal are now entities, this provides a lot of different use cases. A common use case would be to extract an image URI from a media reference field, which you could then access in the pattern lab twig template.

## <a name="getting-started"></a> Getting Started

First, you're going to need to build or use a contributed theme that specializes in organizing patterns. I would recommend using [Emulsify](https://www.drupal.org/project/emulsify), which you can easily setup in a short amount of time. As the project is well documented, and there are plenty of training videos on how to get started.

After you've setup your theme, along with some new patterns using the pattern lab framework. You'll need to define a `*.pattern_library.yml` file, which can live either along side the pattern lab implementation or be contained in a single definition file in the custom theme or module. The pattern provider will be the module or theme name, which would replace the `*` when naming the file.

Next, you'll need to use the directives defined in the [Pattern Definition](#pattern-definition) section to expose the pattern to the Drupal UI. This will create a layout that represents the pattern, and will offload all the markup responsibility to the pattern framework. There is no need to create a twig template override to connect the pattern lab framework to the Drupal theme, the pattern library module takes care of this for you.

If you're needing to manipulate the field data prior to consuming it via the pattern twig template, you can create a custom field formatter. Or you can use the field formatter that's provided with pattern library module, for entity references.

> Throughout this documentation, I'm going to assume you're defining patterns in a theme and using the pattern lab framework. Along with using the display suite module to render the pattern layouts.


## <a name="pattern-discovery"></a> Pattern Discovery


There are two different mechanisms on which patterns are discoverable. The most convenient (and recommend if using pattern lab) is inside the same directory where the twig markup for the atom, molecule, organism, etc. are defined. As this keeps the pattern implementation self contained and encapsulated.

Most Drupal contributed themes, like Emulsify that specialize in pattern lab based projects are using the [Component Libraries](https://www.drupal.org/project/components) module, which the pattern library module has support for. If you're rolling your own theme, be sure to investigate what the components module is all about. The components module is not a hard dependency if you don't want to define pattern definitions within the pattern lab framework.

If you're only using the pattern library module to manage a couple patterns and don't need a framework, you'll be able to define a pattern definition in either the module or theme root directory. After defining a pattern to the Drupal ecosystem you'll need to remember to clear the cache, prior to it showing up in the Drupal UI.


## <a name="pattern-modifiers"></a> Pattern Modifiers 

Layouts defined by the pattern library module are constructed in a way so they're able to collect additional pattern metadata, which we call modifiers. Modifier attributes could be something that changes based on the site-builder or content editors discretion. An example would be: background colors, text justification, background images, and the list goes on. 

If you're using display suite, the modifier options will show up under the layout settings, after selecting a pattern and saving the entity display. You will need to ensure that the pattern you've selected is setup to expose modifiers, as nothing will be shown if it wasn't defined in the [pattern definition](#pattern-definition).

You have the option to map modifiers to a particular field that's been attached to the entity. In most cases you'll hide the modifier fields from the entity display. You can also set the modifier values for a particular entity display without allowing the content editor to alter it, as this is the default behavior.

Modifiers are exposed using the plugin API, so developers can develop their own custom modifiers to capture whatever data their pattern requires. Although the following modifier types are shipped with the pattern library module:

- text
- select
- boolean
- file_path
- image_path

## <a name="pattern-definition"></a> Pattern Definition 

Define a pattern definition using the available directives:

**Required**

- label (string): Define the pattern label.
- source (string): Define the pattern source. This is usually a component alias or path to a twig template.
- variables (array): Define the pattern variables. Which are referred to as regions within the layout realm.

**Optional**

- icon (string) The pattern icon path.
- [modifiers](#pattern-modifiers) (array): Pattern modifers.
  - title (string:required): The modifier title, which shows up in the Drupal UI.
  - type (string:required): The modifier type (refer back to the available modifier types above).
  
  > Note: Depending on the modifier type addition properties may be available.

- libraries (array) 
  - Same structure as [*.libraries.yml](https://www.drupal.org/docs/8/creating-custom-modules/adding-stylesheets-css-and-javascript-js-to-a-drupal-8-module#define-library)
  

**Definition Example (Pattern Lab):**

```yaml
label: Icon
source: @atoms/06-icon/icon.twig
variables:
  url:
    label: URL
  icon:
    label: Icon
modifiers:
  icon_size:
    type: select
    title: Icon Size
    options:
      small: Small
      medium: Medium 
      large: Large 
  icon_link:
    type: boolean
    title: Link Icon
  icon_image:
    type: image_path
    title: Icon Image
  icon_text:
    type: text
    title: Icon Text
libraries:
  js:
    /themes/custom/MYTHEMENAME/js/demo.simple.js: {}
    /themes/custom/MYTHEMENAME/js/demo.complex.min.js: { minified: true }
  css:
    theme:
      /themes/custom/MYTHEMENAME/css/demo.simple.css: {}
  dependencies:
    - core/jquery
```
