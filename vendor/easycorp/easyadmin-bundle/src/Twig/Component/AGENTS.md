# Twig Components

How to build UI components in EasyAdmin with [Symfony UX TwigComponent](https://symfony.com/bundles/ux-twig-component/current/index.html). Read this before adding or changing anything under `src/Twig/Component/` or `templates/components/`.

## Direction (read first)

- We're **incrementally breaking the whole UI into components.** Prefer extracting reusable components over hand-written markup; when you touch a template, consider whether its markup should become — or use — a component.
- **Long-term goal: a shadcn/ui-like design system built on a small set of global design tokens** (radius, spacing, colors, …). We're **not there yet** — the theme is still Bootstrap-based — but **every new component must be built in that spirit** so a future migration is cheap:
  - Prefer **shared design tokens** over one-off props. Drive variants from a small, shared scale, not per-component booleans. The canonical example is the shared **radius scale**: the `Radius` backed enum in `Option/` (shadcn's `none/sm/md/lg/full` scale), rendered through utility classes tied to the `--border-radius*` CSS variables — instead of e.g. a `pill` boolean on a single component. Follow this pattern for new scales. The shared `Size` enum (`sm/md/lg`) in `Option/` plays the same role for component sizes; each component renders its own `{prefix}-{size}` class (`md` emits none) and may support only a subset of the scale.
  - Add to the shared scale instead of inventing a per-component option.
  - When a token is unset, inherit the project default — don't hardcode values.

## Two kinds of components

1. **Anonymous (template-only)** — for pure markup with no logic. Declare inputs with `{% props %}` at the top of `templates/components/<Name>.html.twig`. Examples: `Switch.html.twig`, `ActionMenu`.
2. **Class-backed** — when you need logic, computed values, defaults, or services. PHP class in `src/Twig/Component/<Name>.php` + template in `templates/components/<Name>.html.twig`. Examples: `Alert`, `Badge`, `Button`, `Icon`, `Flag`.

Prefer anonymous components; reach for a class only when there's real logic.

Complex components can be split into **sub-components in a subdirectory** of `templates/components/` (e.g. `ActionMenu/` contains `Button`, `Overlay`, `ActionList/Item`, …), used as `<twig:ea:ActionMenu:Button>`.

**⚠️ No Live Components.** Don't use Symfony UX LiveComponent — it doesn't work in a reusable third-party bundle like this one. Stick to plain TwigComponents; add interactivity with Stimulus or Bootstrap instead.

## Conventions

**Naming & usage.** All components use the `ea` prefix: a class/template named `Alert` is used as `<twig:ea:Alert>`. This is configured once in `EasyAdminExtension::prepend()` (`name_prefix: ea`, `template_directory: @EasyAdmin/components/`) — you don't annotate classes with `#[AsTwigComponent]`.

**Registration (class-backed).** Register the component explicitly in `config/services.php` with the `twig.component` tag. No autowiring — pass dependencies as explicit args, like every service in this bundle:
```php
->set(Alert::class)
    ->tag('twig.component')
```

**Props.**
- Class-backed: declare public typed properties; use `mount()` for coercion/validation of inputs.
- Anonymous: declare `{% props name = default %}`.

**Variants via backed enums.** Put option enums in `src/Twig/Component/Option/` (e.g. `AlertVariant`, `ButtonVariant`). ⚠️ **TwigComponent does NOT auto-coerce string props to enums.** Accept `string|Enum` in `mount()` and coerce with `tryFrom()`, falling back to a default (see `Alert::mount()`):
```php
public function mount(string|AlertVariant $variant): void
{
    $this->variant = \is_string($variant) ? (AlertVariant::tryFrom($variant) ?? AlertVariant::Info) : $variant;
}
```

**Template structure.** Merge props into the root element with `attributes.defaults({...})`, expose slots with `<twig:block name="...">`, and call class methods through `this`:
```twig
<div {{ attributes.defaults({class: this.defaultCssClass(), role: 'alert'}) }}>
    <twig:block name="content"></twig:block>
</div>
```
Compose by calling other components: `<twig:ea:Button ... />`.

**⚠️ Translations in slots.** `{% trans_default_domain %}` set by the caller does NOT reach a component's slot content — the slot is compiled as a separate block scope, so a bare `|trans` falls back to the `messages` domain and renders the raw key (e.g. `label.true`). Always pass the domain explicitly inside slots: `{{ value|trans(domain: 'EasyAdminBundle') }}`. Component *attribute* expressions are evaluated in the caller's scope, so the default domain applies there.

**Styling.** Define design tokens as CSS custom properties in `assets/css/easyadmin-theme/variables-theme.css` (`:root`, with `.ea-dark-scheme` overrides for dark mode); put component styles in a per-component file (e.g. `assets/css/easyadmin-theme/badges.css`, `buttons.css`) imported from the theme. Reuse existing tokens (`--border-radius*`, the color palette) instead of hardcoding values. Class names are `kebab-case`; utilities use the `ea-` prefix. Run `make build-assets` after CSS changes.

**Accessibility & i18n.** `aria-*` attributes, semantic tags, and labels are required; all user-facing text goes through `|trans` (mind the slot caveat above). Icons use FontAwesome 6 names.

**Tests.** Add a unit test in `tests/Unit/Twig/Component/<Name>Test.php` (see `AlertTest`, `BadgeTest`, `IconTest`). Cover `mount()` coercion and any computed output.

## Adding a component — checklist

1. Template in `templates/components/<Name>.html.twig` (`{% props %}` if anonymous).
2. PHP class in `src/Twig/Component/<Name>.php` only if it needs logic; option enums in `Option/`.
3. Register in `config/services.php` with `->tag('twig.component')` (class-backed only).
4. Styles via shared tokens in `variables-theme.css` + a per-component CSS file; `make build-assets`.
5. Use it as `<twig:ea:<Name>>`; build variants from shared scales, not one-off booleans.
6. Unit test in `tests/Unit/Twig/Component/`.
