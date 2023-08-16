Changelog
=========

1.13 (unreleased)
-----------------

- Nothing changed yet.


1.12 (2020-10-07)
-----------------

- Added prefiltering on widgets
  [daggelpop, sgeulette]
- Add Transifex.net service integration to manage the translation process.
  [macagua]
- Add Spanish translation
  [macagua]


1.11 (2019-09-20)
-----------------

- Limit catalog results (with sort_limit) because solr sends None for higher limit results.
  [sgeulette]
- Use contact_source metadata in widget result
  [sgeulette]

1.10 (2017-10-05)
-----------------

- Set Content-type 'text/plain' headers to autocomplete request. This prevent "<!DOCTYPE html" tag.
  [bsuttor]


1.9 (2017-05-30)
----------------

- Fix optimization issue when vocabulary is restricted by a relation.
  [thomasdesvenain]

- Prevent fatal error on autocomplete if by chance a held position related to a position or an organisation has been removed
  but the relation always exist. An error is logged.
  [thomasdesvenain]

1.8 (2016-10-21)
----------------

- ContactChoice can now be used as master field when
  plone.formwidget.masterselect >= 1.6 is installed.
  [vincentfretin]


1.7 (2016-07-07)
----------------

- Set matchSubset: false to fix autocomplete behavior with accents and not
  doing a new ajax request.
  [vincentfretin]

- Ensure that the required property for ContactList field works correctly
  [mpeeters]


1.6 (2016-03-31)
----------------

- Fix an exception with plone.formwidget.contenttree >= 1.0.11 that introduced
  support for providing defaults to contenttrees.
  [pcdummy]


1.5 (2016-03-04)
----------------

- Add a querySelectSearchInput class to the input field.
  [vincentfretin]

- Add display template for single selection field
  [sgeulette]

- Fix buildout
  [sgeulette]

1.4 (2015-06-02)
----------------

- Remove prefill_person when clicking on Create Contact link (this behavior is
  too difficult to understand for end users).
  [cedricmessiant]

- Use a more generic selector for title so that it also works with behaviors.
  [cedricmessiant]

- Use prelabel variable in template (so that you can override it in custom
  settings, see collective.contact.core).
  [cedricmessiant]

- Increase results to 50 items.
  [vincentfretin]

- jQuery 1.9 compatibility.
  [vincentfretin]

- Fix ContactSource search if no review_state parameter
  [ebrehault]


1.2.2 (2014-09-25)
------------------

- Add review_state parameter on ContactList and ContactChoice widgets.
  [cedricmessiant]

1.2.1 (2014-09-10)
------------------

- UI : improve prefill of add new contact overlay form.
  [thomasdesvenain]


1.2 (2014-06-02)
----------------

- We can give as source param a 'relations' value to filter on contents
  related to an other content.
  [thomasdesvenain]


1.1 (2014-03-11)
----------------

- Don't include closeOnClick: true in javascript, so it defaults to
  global configuration.
  [vincentfretin]

- UI improvements :
  - Add contact link is displayed after user has filled a search.
  - We have and explicit help message next to contact link.
  - Contact creation form title is pre-filled with user search.
  - The search input has a placeholder.
  [thomasdesvenain]

- Execute prepOverlay only if it hasn't been done yet, this avoid to have a
  pbo undefined error when you have recursive overlays.
  [vincentfretin]

- The jqueryui autocomplete plugin conflicts with the jquery autocomplete
  plugin used by plone.formwidget.autocomplete, disable the jqueryui one.
  [cedricmessiant]

- Do not break dexterity content type when we don't have a REQUEST
  (in async context).
  [thomasdesvenain]

- We can add contact and contact list fields TTW on dexterity content types.
  [thomasdesvenain]


1.0 (2013-09-18)
----------------

- Check do_post_sort attribute on source to be able to disable the sorting.

- Declare dependencies on z3c.relationfield and plone.formwidget.contenttree.

- Remove ploneform-render-widget view for content provider, this is now
  in plone.app.z3cform since 0.7.3.


1.0rc1 (2013-03-27)
-------------------

- Added hidden and rtf mode templates.
  [vincentfretin]

- Don't open tooltip in tooltip.
  [vincentfretin]


0.12 (2013-03-12)
-----------------

- Decode title, returning unicode, to standardize term attributes
  [sgeulette]


0.11 (2013-03-11)
-----------------

- Fixed UnicodeDecodeError in @@autocomplete-search
  [vincentfretin]

- Internationalized two messages.
  [vincentfretin]

- Don't show tooltip if the mouse left the link.
  [vincentfretin]

- Don't call tokenToUrl if value is --NOVALUE--.
  [vincentfretin]


0.10 (2013-03-07)
-----------------

- Nothing changed yet.


0.9 (2013-03-07)
----------------

- Initial release.
  [vincentfretin]
