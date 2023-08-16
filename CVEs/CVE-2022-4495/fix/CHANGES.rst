Changelog
=========

1.7 (unreleased)
----------------

- Updated columns to work with z3c.table 2.2. Escaped rendering special caracters to avoid xss.
  [sgeulette]

1.6 (2021-04-20)
----------------

- RelatedDocs field can handle object_provides criteria.
  [sgeulette]

1.5 (2019-11-25)
----------------

- Added css on some columns.
  [sgeulette]

1.4 (2019-09-20)
----------------

- Made class inheritance clearer.
  [sgeulette]

1.3 (2018-09-24)
----------------

- Use a fade edit pencil to dissuade user click on it.
  [sgeulette]

1.2 (2018-09-05)
----------------

- Changed french translation to avoid confusion.
  [sgeulette]

1.1 (2018-07-23)
----------------

- Remove filerepresentation adapters.
  They are not needed for collective.zopeedit > 1.0.0
  [gotcha]
- Replace restrictedTraverse by getMultiAdapter
  [sgeulette]
- Display related docs with list
  [sgeulette]

1.0 (2017-06-02)
----------------

- Replace collective.z3cform.rolefield by dexterity.localrolesfield. Manual configuration needed. See readme...
  [sgeulette]

0.7 (2017-05-30)
----------------

- Move the signed attribute to collective.dms.scanbehavior.
  [mpeeters]

0.6 (2015-11-24)
----------------

- Removed old sorting attribute. Added dependency. Changed travis config.
  [sgeulette]

0.5 (2015-06-02)
----------------

- Added div with id to fix fields display in edit mode
  [sgeulette]
- Added treating_groups and recipient_groups catalog index.
  [sgeulette]
- Added treating_groups and recipient_groups in p.a.collection columns
  [sgeulette]
- Cleaning on task old stuff
  [cmessiant]
- Don't use AjaxChosenMultiFieldWidget for treating_groups field
  [cmessiant]

0.4 (2015-03-13)
----------------

- Fix fields width to 50% to keep fields on the left of the scan preview
  [sgeulette]
- Test attribute existence to resolve a recatalog problem
  [sgeulette]

0.3 (2014-10-24)
----------------

- Correct wrong metadata name in column
  [sgeulette]
- Add a dmsdocument edit view including documentviewer to complete attributes after batch import.
  [sgeulette]

0.2 (2014-02-26)
----------------

- Update the LocalRolesToPrincipals fields to use zope.schemaevent
  [mpeeters]
- Integrated documentviewer
  [vincentfretin]
- Add signed version
  [vincentfretin, cedricmessiant]
- New default view for all documents
  [vincentfretin, cedricmessiant]
- Allow tasks to be added to document
  [fpeters, vincentfretin, cedricmessiant]

0.1.1 (2013-03-08)
------------------

- Corrected MANIFEST.in

0.1 (2013-03-06)
----------------

- Package created using templer
  [cedricmessiant]
- Added portal types
  [sgeulette]
- Related field
  [davidconvent]
- LocalRolesToPrincipals field
  [gauthierbastien]
