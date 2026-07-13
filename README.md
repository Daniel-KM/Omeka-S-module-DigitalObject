Digital Object (module for Omeka S)
===================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Digital Object] is a module for [Omeka S] that allows to manage digital objects,
that are autonomous resource with a file and that are attacheable to any
resource. It allows to import files then to attach them to an item. It may
replace medias and allows to share one file between multiple items. So digital
objects can be linked like any remote resources.

So the module answers to common requests:
- I want to import all my scanned files first.
- I want to attach the same files to multiple items.
- I want to attach my local and remote assets as standard rdf metadata to items.

A migration job allows to switch and convert existing media to/from digital
objects.


Installation
------------

See general end user documentation for [installing a module].

This module requires the module [Common], that should be installed first.

- From the zip

Download the last release [DigitalObject.zip] from the list of releases, and
uncompress it in the `modules` directory.

- From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `DigitalObject`, go to the root of the module, and run:

```sh
composer install --no-dev
```

Then install it like any other Omeka module and follow the config instructions.

- For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/DigitalObject/phpunit.xml --testdox
```


Usage
-----

A digital object is created from the add form, either by uploading a local file
or by providing a URL. When the URL points to a IIIF Image `info.json`, the
object keeps no local file and is served from the remote image service; when it
points to a plain file, the file is downloaded and stored locally.

To attach a digital object to a resource, go to the item edit form, then add a
value with a resource, then click "digital object". Of course, it is simpler to
configure templates with the data type `resource:digitalobject`.

The link is expressed as a standard RDF Value, using any property. As resource,
the digital object thumbnail will be displayed anywhere. Nevertheless, only the
digital objects belonging to a list of specified properties will be rendered
like medias in the item page. So, this setting is the most important to be set
in config form.

The defaults are `dcterms:hasFormat`, for digital surrogates of an item: scan of
a book, PDF of an article…, and `dcterms:hasPart`, for digital objects that are
components of an item, for example one scanned file per page in a multi-page
book, per figure, per chapter….

Choosing the right one preserves the semantics for linked-data consumers; the
module itself treats both lists identically. Standard candidates, by use case:

| Vocabulary    | Property                                                      | Use case                                                |
|---------------|------------------------------------------------------------- -|---------------------------------------------------------|
| Dublin Core   | `dcterms:hasFormat` (default, "another format of the whole")  | Generic, neutral on media type and described entity     |
| Dublin Core   | `dcterms:hasPart` (default, "component of the whole")         | Generic, neutral on media type and described entity     |
| BIBFRAME      | `bf:hasInstance` / `bf:hasItem` / `bf:electronicLocator`      | Libraries (Library of Congress, MARC successor)         |
| RDA Registry  | `rdaw:hasManifestationOfWork` / `rdam:hasItem`                | Libraries (Resource Description and Access)             |
| CIDOC-CRM     | `crm:P138i_is_represented_by`                                 | Museum collections, strict ontology                     |
| RiC-O         | `rico:hasInstantiation` / `rico:hasOrHadDigitalInstantiation` | Archives (Records in Contexts)                          |
| EDM           | `edm:isShownBy` / `edm:hasView`                               | Europeana / patrimoine aggregator                       |
| PCDM          | `pcdm:hasFile`                                                | Fedora / Islandora / Hydra                              |
| BIBO / PREMIS | `bibo:transcriptOf` / `premis:hasOriginal`                    | Derivative roles (OCR, transcription, restored version) |
| FOAF          | `foaf:depiction`                                              | Image only (person-leaning vocabulary)                  |
| schema.org    | `schema:image` / `schema:associatedMedia` / `schema:encoding` | Web-friendly, SEO                                       |


Rendering and thumbnails
------------------------

A digital object renders like a media, according to its media type: an image is
displayed as an `<img>`, an audio as `<audio>`, a video as `<video>`, and any
other type as a download link. It acts as its own primary media, so it feeds the
core thumbnail pipeline directly, without any wrapping media.

When the digital object is an IIIF Image (or converted from file to remote IIIF
service via the module [Iiif From File]), it keeps no local file but stores the
remote `info.json` as its data. In that case it is rendered like media IIIF Image,
through OpenSeadragon and its thumbnails are resolved from the remote IIIF image
service, so the object still displays everywhere, even without local original
file.

Because a digital object is its own primary media, container modules can use it
as the thumbnail of a resource that has no native media. When the primary media
of a resource is empty, the modules trigger the event `rep.resource.primary_media`
so a linked digital object can be used as a fallback. It is used in particular
in module [Selection] and [Contribute]. The event receives the resource as
`resource` and lets a listener set the `primary` argument.


Notes for developers
--------------------

The module does not replicate the full Media ingester/renderer architecture (no
`ingester`/`renderer` columns, no pluggable manager). The content nature is
exposed by a single `type()` method (`file`, `iiif-image`, `iiif-presentation`),
from which `ingester()`/`renderer()` are derived. Unlike Media, a digital object
has no ingester/renderer duality, so one `type` drives ingestion, rendering and
the admin display.

The type is detected, not stored uniformly, because the storage must avoid two
constraints. (1) A IIIF Image `info.json` is stored raw as the data so that
IiifServer and OpenSeadragon read it directly; it is self-describing, so the type
is detected from its `@context`/`protocol` and no control key is added — adding a
`type` key would collide with the info.json own `type` (e.g. `ImageService3`).
(2) IiifServer persists dimensions through `mediaData()` = `getData()` at the
root, so everything stays at the data root (no sub-key). A IIIF Presentation
manifest is not stored, so its data carries only the `type` discriminant (no
payload, no possible collision) plus the extracted thumbnail.

`ingester()` is always `digital_object`: a digital object has no pluggable
ingestion, so this only identifies the resource kind. The IIIF behaviour is
carried by `renderer()`, which mirrors the core Media IIIF renderer names —
`iiif` (image), `iiif_presentation` (manifest), `file` otherwise. Consumers must
test `renderer()`, not `ingester()`, to recognize a IIIF source; the IIIF-related
modules were updated accordingly (IiifServer `IsIiifMedia`, `ContentResource`,
`TraitMedia`, `MediaDimension(s)`; IiifFromFile; Diva; Mirador; Cartography;
SearchSolr for `html`). For media this is equivalent (their ingester and renderer
share the same names); for digital objects it is required.

There is no `media_type` for IIIF objects: like the core IIIF media ingester,
none is set, because a IIIF service is not a file with a single MIME type.
`media_type` stays the real MIME of actual files only.

Two IIIF kinds are handled when a URL is ingested:

- IIIF Image API (a remote `info.json`): stored raw as the data (self-describing)
  and rendered through OpenSeadragon, like a core IIIF Image media.
- IIIF Presentation API (a remote manifest): stored as `data['type'] = 'iiif-presentation'`
  and rendered in the core IIIF viewer (`iiifViewer()`, as `Omeka\Media\Renderer\IiifPresentation`
  does). The manifest thumbnail is extracted (manifest level, else first canvas)
  into `data['thumbnail']` so the object still shows a thumbnail in lists.

Dimensions are not computed by this module and are never nested inside the IIIF
payload. When present they live at the data root (`data['dimensions']`), produced
by IiifServer (`MediaDimension`/`MediaDimensions`, which already handles digital
objects) or inherited from a converted media; a partial set (e.g. only
`original`/`medium`) just reflects an incomplete external run. For a IIIF Image
the dimensions are already in the info.json (`width`/`height`), so storing them
again is redundant; for a IIIF Presentation they come from the manifest canvas.

A manifest describes a compound object, not a single file, so referencing one as
a digital object is mostly useful to attach a remote object (for example an item
or a collection of another institution) to an item set. For a local compound
object, prefer an item served by IiifServer.


TODO
----

- [ ] No site attachment. Unlike items (`item_site`) and item sets
      (`site_item_set`), digital objects are not attached to sites and
      `buildQuery()` adds no site filter: a public digital object is visible from
      any site. This is intentional — a digital object is autonomous and is
      reached through the items that reference it, which already carry the site
      scope; duplicating a per-site attachment would be redundant and would split
      the same shared object across sites. Site visibility therefore relies only
      on `is_public` plus the Access module (which handles digital objects like
      any other resource). Revisit only if per-site scoping of digital objects is
      ever required (would need a `digital_object_site` table, a `site_id` filter
      in `buildQuery()`, and the assignment UI).
- [ ] No resource stub. Unlike items (`addItemStubAction`, a minimal
      title-only resource created on the fly to be attached as a `resource`
      value), a digital object cannot be a stub: it requires a content (file,
      URL or sideload) at creation and that content is immutable, so a content
      less stub would fail validation and stay forever unusable. A fast
      "create from URL" sidebar (IIIF/remote file, reusing `hydrateUrl()`) would
      be possible if quick attachment is ever needed, but a minimal stub is
      ruled out.
- [ ] SSRF hardening of URL ingestion. `fetchIiifJson()` (HEAD+GET) and the core
      `Downloader` fetch any user-supplied `ingest_url` without validating the
      scheme or host, so a user with the `create` right could make the server
      request internal targets (`localhost`, `169.254.169.254` cloud metadata,
      private ranges). This is inherited from the core URL/IIIF ingesters (same
      behaviour), and limited to authorized users, so it is left as-is for now.
      If hardened: restrict to `http`/`https` and block loopback/private IPs
      (resolve the host first), ideally in a shared helper so the core ingesters
      benefit too.
- [ ] Replace media or exclude mixed database?
- [ ] Option to create the thumbnail locally for a IIIF Image (download a
      derivative such as `full/!400,400/0/default.jpg` from the remote service at
      ingest, store it as the Omeka thumbnail, set `has_thumbnails = true`).
      It can be mixed with the remote service: local thumbnail for fast listings,
      remote `info.json` kept for the OpenSeadragon zoom. Mixing does not slow
      rendering — the thumbnail is served locally and the remote service is only
      hit when the viewer opens; the single extra cost is one download at ingest.
- [ ] Core form element `Omeka\Form\Element\Query::getResourceType()` hardcodes `['items', 'item_sets', 'media']` in application/src/Form/Element/Query.php.
      As a result, query preview selectors built on this element silently fall back to `items` when configured with `query_resource_type = digital_objects`.
      The underlying queries still work (API and parse_str handle the resource name), only the form preview is affected. Fix requires a core patch.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.

```sh
# database dump example
mysqldump -u omeka -p omeka | gzip > "omeka.$(date +%Y%m%d_%H%M%S).sql.gz"
```


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

- Copyright Daniel Berthereau, 2026 (see [Daniel-KM] on GitLab)

This module was built for the future digital library of the [Musée de Bretagne],
currently managed with a proprietary software.


[Digital Object]: https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject
[Omeka S]: https://omeka.org/s
[installing a module]: https://omeka.org/s/docs/user-manual/modules/
[DigitalObject.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject/-/releases
[Iiif From File]: https://gitlab.com/Daniel-KM/Omeka-S-module-IiifFromFile
[Selection]: https://gitlab.com/Daniel-KM/Omeka-S-module-Selection
[Contribute]: https://gitlab.com/Daniel-KM/Omeka-S-module-Contribute
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Musee de Bretagne]: http://collections.musee-bretagne.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
