Digital Object (module for Omeka S)
===================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Digital Object] is a module for [Omeka S] that allows to manage digital object,
that are files attacheable to any resource. It allows to import files then to
attach them to an item. It may replace medias and allows to share one file
between multiple items.


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

To attach a digital object to a resource, use the data type `resource:digitalobject`
(or the generic `resource` data type).

The link is expressed as a standard RDF Value, using any property the
admin chooses. The setting `digitalobject_inline_properties` lists which
properties trigger inline rendering of the referenced digital objects in
the item display. The defaults are `dcterms:hasFormat` (for digital
surrogates of an item — scan of a book, PDF of an article…) and
`dcterms:hasPart` (for digital objects that are *components* of an item —
one DO per page in a multi-page book, per figure, per chapter…).
Choosing the right one preserves the semantics for linked-data
consumers; the module itself treats both lists identically. Standard
candidates, by use case:

| Vocabulary    | Property                                                      | Use case                                                |
|---------------|------------------------------------------------------------- -|---------------------------------------------------------|
| Dublin Core   | `dcterms:hasFormat` (default, "another format of the whole") / `dcterms:hasPart` (default, "component of the whole") / `dcterms:relation` | Generic, neutral on media type and described entity     |
| BIBFRAME      | `bf:hasInstance` / `bf:hasItem` / `bf:electronicLocator`      | Libraries (Library of Congress, MARC successor)         |
| RDA Registry  | `rdaw:hasManifestationOfWork` / `rdam:hasItem`                | Libraries (Resource Description and Access)             |
| CIDOC-CRM     | `crm:P138i_is_represented_by`                                 | Museum collections, strict ontology                     |
| RiC-O         | `rico:hasInstantiation` / `rico:hasOrHadDigitalInstantiation` | Archives (Records in Contexts)                          |
| EDM           | `edm:isShownBy` / `edm:hasView`                               | Europeana / patrimoine aggregator                       |
| PCDM          | `pcdm:hasFile`                                                | Fedora / Islandora / Hydra                              |
| BIBO / PREMIS | `bibo:transcriptOf` / `premis:hasOriginal`                    | Derivative roles (OCR, transcription, restored version) |
| FOAF          | `foaf:depiction`                                              | Image only (person-leaning vocabulary)                  |
| schema.org    | `schema:image` / `schema:associatedMedia` / `schema:encoding` | Web-friendly, SEO                                       |


TODO
----

- [ ] Replace media?


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
[DigitalObject.zip]: https://github.com/Daniel-KM/Omeka-S-module-DigitalObject/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Musee de Bretagne]: http://collections.musee-bretagne.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
