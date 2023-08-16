# -*- coding: utf-8 -*-
#
# Copyright (C) 2020-2021 CERN.
# Copyright (C) 2020 Northwestern University.
#
# Invenio-Drafts-Resources is free software; you can redistribute it and/or
# modify it under the terms of the MIT License; see LICENSE file for more
# details.

"""Primary service for working with records and drafts."""

from elasticsearch_dsl.query import Q
from invenio_db import db
from invenio_records_resources.services import LinksTemplate
from invenio_records_resources.services import \
    RecordService as RecordServiceBase
from invenio_records_resources.services import ServiceSchemaWrapper
from invenio_records_resources.services.uow import RecordCommitOp, \
    RecordDeleteOp, RecordIndexOp, unit_of_work
from sqlalchemy.orm.exc import NoResultFound


class RecordService(RecordServiceBase):
    """Record and draft service interface.

    This service provides an interface to business logic for published and
    draft records.
    """

    def __init__(self, config, files_service=None, draft_files_service=None):
        """Constructor for RecordService."""
        super().__init__(config)
        self._files = files_service
        self._draft_files = draft_files_service

    #
    # Subservices
    #
    @property
    def files(self):
        """Record files service."""
        return self._files

    @property
    def draft_files(self):
        """Draft files service."""
        return self._draft_files

    #
    # Properties
    #
    @property
    def schema_parent(self):
        """Schema for parent records."""
        return ServiceSchemaWrapper(self, schema=self.config.schema_parent)

    @property
    def draft_cls(self):
        """Factory for creating a record class."""
        return self.config.draft_cls

    # High-level API
    # Inherits record search, read, create, delete and update

    def update(self, *args, **kwargs):
        """Do not use."""
        raise NotImplementedError("Records should be updated via their draft.")

    def search_drafts(self, identity, params=None, es_preference=None,
                      **kwargs):
        """Search for drafts records matching the querystring."""
        self.require_permission(identity, 'search_drafts')

        # Prepare and execute the search
        params = params or {}

        search_result = self._search(
            'search_drafts',
            identity,
            params,
            es_preference,
            record_cls=self.draft_cls,
            search_opts=self.config.search_drafts,
            # `has_draft` systemfield is not defined here. This is not ideal
            # but it helps avoid overriding the method. See how is used in
            # https://github.com/inveniosoftware/invenio-rdm-records
            extra_filter=Q('term', has_draft=False),
            permission_action='read_draft',
            **kwargs
        ).execute()

        return self.result_list(
            self,
            identity,
            search_result,
            params,
            links_tpl=LinksTemplate(self.config.links_search_drafts, context={
                "args": params
            }),
            links_item_tpl=self.links_item_tpl,
        )

    def search_versions(self, id_, identity, params=None, es_preference=None,
                        **kwargs):
        """Search for record's versions."""
        try:
            record = self.record_cls.pid.resolve(id_, registered_only=False)
        except NoResultFound:
            record = self.draft_cls.pid.resolve(id_, registered_only=False)

        self.require_permission(identity, "read", record=record)

        # Prepare and execute the search
        params = params or {}

        search_result = self._search(
            'search_versions',
            identity,
            params,
            es_preference,
            record_cls=self.record_cls,
            search_opts=self.config.search_versions,
            extra_filter=Q(
                'term', **{'parent.id': str(record.parent.pid.pid_value)}),
            permission_action='read',
            **kwargs
        ).execute()

        return self.result_list(
            self,
            identity,
            search_result,
            params,
            links_tpl=LinksTemplate(
                self.config.links_search_versions,
                context={"id": id_, "args": params}
            ),
            links_item_tpl=self.links_item_tpl,
        )

    def read_draft(self, id_, identity):
        """Retrieve a draft."""
        # Resolve and require permission
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)
        self.require_permission(identity, "read_draft", record=draft)

        # Run components
        for component in self.components:
            if hasattr(component, 'read_draft'):
                component.read_draft(identity, draft=draft)

        return self.result_item(
            self, identity, draft, links_tpl=self.links_item_tpl)

    def read_latest(self, id_, identity):
        """Retrieve latest record."""
        # Resolve and require permission
        record = self.record_cls.pid.resolve(id_)

        # Retrieve latest if record is not
        if not record.versions.is_latest:
            record = self.record_cls.get_record(record.versions.latest_id)

        self.require_permission(identity, "read", record=record)

        return self.result_item(
            self, identity, record, links_tpl=self.links_item_tpl)

    @unit_of_work()
    def update_draft(self, id_, identity, data, revision_id=None, uow=None):
        """Replace a draft."""
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)

        self.check_revision_id(draft, revision_id)

        # Permissions
        self.require_permission(identity, "update_draft", record=draft)

        # Load data with service schema
        data, errors = self.schema.load(
            data,
            context=dict(
                identity=identity,
                pid=draft.pid,
                record=draft,
            ),
            # Saving a draft only saves valid metadata and reports
            # (doesn't raise) errors
            raise_errors=False
        )

        # Run components
        self.run_components(
            'update_draft', identity, record=draft, data=data,
            errors=errors, uow=uow
        )

        # Commit and index
        uow.register(RecordCommitOp(draft, indexer=self.indexer))

        return self.result_item(
            self,
            identity,
            draft,
            links_tpl=self.links_item_tpl,
            errors=errors
        )

    @unit_of_work()
    def create(self, identity, data, uow=None):
        """Create a draft for a new record.

        It does NOT eagerly create the associated record.
        """
        res = self._create(
           self.draft_cls,
           identity,
           data,
           raise_errors=False,
           uow=uow,
        )
        uow.register(RecordCommitOp(res._record.parent))
        return res

    @unit_of_work()
    def edit(self, id_, identity, uow=None):
        """Create a new revision or a draft for an existing record.

        :param id_: record PID value.
        """
        # Draft exists - return it
        try:
            draft = self.draft_cls.pid.resolve(id_, registered_only=False)
            self.require_permission(identity, "edit", record=draft)
            return self.result_item(
                self, identity, draft, links_tpl=self.links_item_tpl)
        except NoResultFound:
            pass

        # Draft does not exists - so get the main record we want to edit and
        # create a draft from it
        record = self.record_cls.pid.resolve(id_)
        self.require_permission(identity, "edit", record=record)
        draft = self.draft_cls.edit(record)

        # Run components
        self.run_components(
            "edit", identity, draft=draft, record=record, uow=uow)

        uow.register(RecordCommitOp(draft, indexer=self.indexer))

        # Reindex the record to trigger update of computed values in the
        # available dumpers of the record.
        uow.register(RecordIndexOp(record, indexer=self.indexer))

        return self.result_item(
            self, identity, draft, links_tpl=self.links_item_tpl)

    @unit_of_work()
    def publish(self, id_, identity, uow=None):
        """Publish a draft.

        Idea:
            - Get the draft from the data layer (draft is not passed in)
            - Validate it more strictly than when it was originally saved
              (drafts can be incomplete but only complete drafts can be turned
              into records)
            - Create or update associated (published) record with data
        """
        self.require_permission(identity, "publish")

        # Get the draft
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)

        # Validate the draft strictly - since a draft can be saved with errors
        # we do a strict validation here to make sure only valid drafts can be
        # published.
        self._validate_draft(identity, draft)

        # Create the record from the draft
        latest_id = draft.versions.latest_id
        record = self.record_cls.publish(draft)

        # Run components
        self.run_components(
            'publish', identity, draft=draft, record=record, uow=uow)

        # Commit and index
        uow.register(RecordCommitOp(record, indexer=self.indexer))
        uow.register(RecordDeleteOp(draft, force=False, indexer=self.indexer))

        if latest_id:
            self._reindex_latest(latest_id, uow=uow)

        return self.result_item(
            self, identity, record, links_tpl=self.links_item_tpl)

    @unit_of_work()
    def new_version(self, id_, identity, uow=None):
        """Create a new version of a record."""
        # Get the a record - i.e. you can only create a new version in case
        # at least one published record already exists.
        record = self.record_cls.pid.resolve(id_)

        # Check permissions
        self.require_permission(identity, "new_version", record=record)

        # Draft for new version already exists? if so return it
        if record.versions.next_draft_id:
            next_draft = self.draft_cls.get_record(
                record.versions.next_draft_id)
            return self.result_item(
                self, identity, next_draft, links_tpl=self.links_item_tpl)

        # Draft for new version does not exists, so create it
        next_draft = self.draft_cls.new_version(record)

        # Get the latest published record if it's not the current one.
        if not record.versions.is_latest:
            record = self.record_cls.get_record(record.versions.latest_id)

        # Run components
        self.run_components(
            'new_version', identity, draft=next_draft, record=record, uow=uow)

        # Commit and index
        uow.register(RecordCommitOp(next_draft, indexer=self.indexer))

        self._reindex_latest(
            next_draft.versions.latest_id, record=record, uow=uow)

        return self.result_item(
            self, identity, next_draft, links_tpl=self.links_item_tpl)

    @unit_of_work()
    def delete_draft(self, id_, identity, revision_id=None, uow=None):
        """Delete a record from database and search indexes."""
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)
        latest_id = draft.versions.latest_id

        self.check_revision_id(draft, revision_id)

        # Permissions
        self.require_permission(identity, "delete_draft", record=draft)

        # Get published record if exists
        try:
            record = self.record_cls.get_record(draft.id)
        except NoResultFound:
            record = None

        # We soft-delete a draft when a published record exists, in order to
        # keep the version_id counter around for optimistic concurrency
        # control (both for ES indexing and for REST API clients)
        force = False if record else True

        # Run components
        self.run_components(
            'delete_draft', identity, draft=draft, record=record,
            force=force, uow=uow
        )

        # Note, the parent record deletion logic is implemented in the
        # ParentField and will automatically take care of deleting the parent
        # record in case this is the only draft that exists for the parent.
        # We refresh the index because users are usually redirected to a
        # search result immediately after, and we don't want the users to see
        # their just deleted draft.
        uow.register(RecordDeleteOp(
            draft, indexer=self.indexer, force=force, index_refresh=True))

        if force:
            # Case 1: We deleted a new draft (without a published record) or a
            # new version draft (without a published).
            # In this case, we reindex the latest published record/draft
            self._reindex_latest(latest_id, refresh=True, uow=uow)
        else:
            # Case 2: We deleted a draft for a published record.
            # In this case we reindex just the published record to trigger and
            # update of computed values.
            uow.register(RecordIndexOp(
                record, indexer=self.indexer, index_refresh=True))

        return True

    @unit_of_work()
    def import_files(self, id_, identity, uow=None):
        """Import files from previous record version."""
        if self.draft_files is None:
            raise RuntimeError("Files support is not enabled.")

        # Read draft
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)
        self.require_permission(identity, "draft_create_files", record=draft)

        # Retrieve latest record
        record = self.record_cls.get_record(draft.versions.latest_id)
        self.require_permission(identity, "read_files", record=record)

        # Run components
        self.run_components(
            'import_files', identity, draft=draft, record=record, uow=uow)

        # Commit and index
        uow.register(RecordCommitOp(draft, indexer=self.indexer))

        return self.draft_files.file_result_list(
            self.draft_files,
            identity,
            results=draft.files.values(),
            record=draft,
            links_tpl=self.draft_files.file_links_list_tpl(id_),
            links_item_tpl=self.draft_files.file_links_item_tpl(id_),
        )

    def rebuild_index(self, identity):
        """Reindex all records and drafts.

        Note: Skips (soft) deleted records and drafts.
        """
        ret_val = super().rebuild_index(identity)

        for draft_meta in self.draft_cls.model_cls.query.all():
            draft = self.draft_cls(draft_meta.data, model=draft_meta)
            if not draft.is_deleted:
                self.indexer.index(draft)

        return ret_val

    def validate_draft(self, identity, id_):
        """Validate a draft."""
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)
        self._validate_draft(identity, draft)

    def _validate_draft(self, identity, draft):
        """Validate a draft.

        This method is internal because it works with a data access layer
        draft, and thus should not be called from outside the service.
        """
        # Convert to draft into service layer draft result item (a record
        # projection for the given identity). This way we can load and validate
        # the data with the service schema.
        draft_item = self.result_item(self, identity, draft)
        # Validate the data - will raise ValidationError if not valid.
        self.schema.load(
            data=draft_item.data,
            context=dict(
                identity=identity,
                pid=draft.pid,
                record=draft,
            ),
            raise_errors=True  # this is the default, but might as well be
                               # explicit
        )

    @unit_of_work()
    def _reindex_latest(self, latest_id, record=None, draft=None,
                        refresh=False, uow=None):
        """Reindex the latest published record and draft.

        This triggers and update of computed values in the index, such as
        "is_latest".

        This method is internal because it works with a data access layer
        record/draft, and thus should not be called from outside the service.
        """
        # We only have a draft, no latest to index
        if not latest_id:
            return

        # Note, the record may not be the latest published record, and we only
        # want to index the latest published.
        if record is None or latest_id != record.id:
            record = self.record_cls.get_record(latest_id)
        uow.register(
            RecordIndexOp(record, indexer=self.indexer, index_refresh=refresh))

        # Note, a draft may or may not exists for a published record (depending
        # on if it's being edited).
        try:
            draft = self.draft_cls.get_record(latest_id)
            uow.register(RecordIndexOp(
                draft, indexer=self.indexer, index_refresh=refresh))
        except NoResultFound:
            pass

    def _get_record_and_parent_by_id(self, id_):
        """Resolve the record and its parent, by the given ID.

        If the ID belongs to a parent record, no child record will be
        resolved.
        """
        record = self.record_cls.pid.resolve(id_, registered_only=False)
        parent = record.parent

        return record, parent

    def _get_draft_and_parent_by_id(self, id_):
        """Resolve the draft and its parent, by the given ID."""
        draft = self.draft_cls.pid.resolve(id_, registered_only=False)
        parent = draft.parent

        return draft, parent

    @unit_of_work()
    def _index_related_records(self, record, parent, uow=None):
        """Index all records that are related to the specified ones."""
        siblings = self.record_cls.get_records_by_parent(
            parent or record.parent
        )

        # TODO only index the current record immediately;
        #      all siblings should be sent to a high-priority celery task
        #      instead (requires bulk indexing to work)
        for sibling in siblings:
            uow.register(RecordIndexOp(sibling, indexer=self.indexer))

    @unit_of_work()
    def cleanup_drafts(self, timedelta, uow=None):
        """Hard delete of soft deleted drafts.

        :param int timedelta: timedelta that should pass since
            the last update of the draft in order to be hard deleted.
        """
        self.draft_cls.cleanup_drafts(timedelta)
