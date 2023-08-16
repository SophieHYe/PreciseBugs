/*
 * Copyright (C) 2006-2019 Talend Inc. - www.talend.com
 *
 * This source code is available under agreement available at
 * %InstallDIR%\features\org.talend.rcp.branding.%PRODUCTNAME%\%PRODUCTNAME%license.txt
 *
 * You should have received a copy of the agreement along with this program; if not, write to Talend SA 9 rue Pages
 * 92150 Suresnes, France
 */

package com.amalto.core.storage;

import static com.amalto.core.query.user.UserQueryBuilder.eq;
import static com.amalto.core.query.user.UserQueryBuilder.from;

import java.io.IOException;
import java.io.StringReader;
import java.util.ArrayList;
import java.util.Collection;
import java.util.Collections;
import java.util.HashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Set;

import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.parsers.ParserConfigurationException;

import org.apache.commons.lang.StringUtils;
import org.apache.logging.log4j.Logger;
import org.apache.logging.log4j.LogManager;
import org.talend.mdm.commmon.metadata.ComplexTypeMetadata;
import org.talend.mdm.commmon.metadata.ContainedComplexTypeMetadata;
import org.talend.mdm.commmon.metadata.DefaultMetadataVisitor;
import org.talend.mdm.commmon.metadata.FieldMetadata;
import org.talend.mdm.commmon.metadata.MetadataRepository;
import org.talend.mdm.commmon.metadata.MetadataVisitor;
import org.talend.mdm.commmon.metadata.TypeMetadata;
import org.talend.mdm.commmon.util.core.MDMXMLUtils;
import org.talend.mdm.commmon.util.webapp.XSystemObjects;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.xml.sax.InputSource;
import org.xml.sax.XMLReader;

import com.amalto.core.metadata.ClassRepository;
import com.amalto.core.objects.ObjectPOJO;
import com.amalto.core.query.user.Select;
import com.amalto.core.query.user.UserQueryBuilder;
import com.amalto.core.server.StorageAdmin;
import com.amalto.core.storage.record.DataRecord;
import com.amalto.core.storage.record.DataRecordReader;
import com.amalto.core.storage.record.XmlDOMDataRecordReader;
import com.amalto.core.storage.record.XmlSAXDataRecordReader;
import com.amalto.xmlserver.interfaces.ItemPKCriteria;
import com.amalto.xmlserver.interfaces.XmlServerException;

public class SystemStorageWrapper extends StorageWrapper {

    private static final DocumentBuilderFactory DOCUMENT_BUILDER_FACTORY = DocumentBuilderFactory.newInstance();

    private static final String SYSTEM_PREFIX = "amaltoOBJECTS"; //$NON-NLS-1$

    private static final String CUSTOM_FORM_TYPE = "custom-form-pOJO"; //$NON-NLS-1$

    private static final String DROPPED_ITEM_TYPE = "dropped-item-pOJO"; //$NON-NLS-1$

    private static final String COMPLETED_ROUTING_ORDER = "completed-routing-order-v2-pOJO"; //$NON-NLS-1$

    private static final String FAILED_ROUTING_ORDER = "failed-routing-order-v2-pOJO"; //$NON-NLS-1$

    private static final String SYNCHRONIZATION_OBJECT_TYPE = "synchronization-object-pOJO"; //$NON-NLS-1$

    private static final String BROWSEITEM_PREFIX_INFO = "SearchTemplate.BrowseItem."; //$NON-NLS-1$

    private static final Logger LOGGER = LogManager.getLogger(SystemStorageWrapper.class);

    public SystemStorageWrapper() {
        try {
            DOCUMENT_BUILDER_FACTORY.setNamespaceAware(true);
            DOCUMENT_BUILDER_FACTORY.setFeature(MDMXMLUtils.FEATURE_DISALLOW_DOCTYPE, true);
        } catch (ParserConfigurationException e) {
            throw new RuntimeException("Unable to initialize document builder.", e);
        }
        // Create "system" storage
        StorageAdmin admin = getStorageAdmin();
        if (!admin.exist(StorageAdmin.SYSTEM_STORAGE, StorageType.SYSTEM)) {
            String datasource = admin.getDatasource(StorageAdmin.SYSTEM_STORAGE);
            admin.create(StorageAdmin.SYSTEM_STORAGE, StorageAdmin.SYSTEM_STORAGE, StorageType.SYSTEM, datasource);
        }
    }

    private ComplexTypeMetadata getType(String clusterName, Storage storage, String uniqueID) {
        MetadataRepository repository = storage.getMetadataRepository();
        if (uniqueID != null && uniqueID.startsWith("amalto_local_service_")) { //$NON-NLS-1$
            return repository.getComplexType("service-bMP"); //$NON-NLS-1$
        }
        if (clusterName.startsWith(SYSTEM_PREFIX) || clusterName.startsWith("amalto")) { //$NON-NLS-1$
            if (!"amaltoOBJECTSservices".equals(clusterName)) { //$NON-NLS-1$
                return repository.getComplexType(ClassRepository.format(clusterName.substring(SYSTEM_PREFIX.length()) + "POJO")); //$NON-NLS-1$
            } else {
                return repository.getComplexType(ClassRepository.format(clusterName.substring(SYSTEM_PREFIX.length())));
            }
        }
        if (XSystemObjects.DC_MDMITEMSTRASH.getName().equals(clusterName)) {
            return repository.getComplexType(DROPPED_ITEM_TYPE);
        } else if (XSystemObjects.DC_PROVISIONING.getName().equals(clusterName)) {
            String typeName = getTypeName(uniqueID);
            if ("Role".equals(typeName)) { //$NON-NLS-1$
                return repository.getComplexType("role-pOJO"); //$NON-NLS-1$
            }
            return repository.getComplexType(typeName);
        } else if ("MDMDomainObjects".equals(clusterName) || "MDMItemImages".equals(clusterName) || "FailedAutoCommitSvnMessage".equals(clusterName)) { //$NON-NLS-1$ //$NON-NLS-2$ //$NON-NLS-3$
            return null; // Documents for these clusters don't have a predefined structure.
        }
        // No id, so no type to be read.
        if (uniqueID == null) {
            return null;
        }
        // MIGRATION.completed.record
        return repository.getComplexType(getTypeName(uniqueID));
    }

    @Override
    public List<String> getItemPKsByCriteria(ItemPKCriteria criteria) throws XmlServerException {
        String clusterName = criteria.getClusterName();
        Storage storage = getStorage(clusterName);
        MetadataRepository repository = storage.getMetadataRepository();

        int totalCount = 0;
        List<String> itemPKResults = new LinkedList<String>();
        String typeName = criteria.getConceptName();

        try {
            storage.begin();
            if (typeName != null && !typeName.isEmpty()) {
                String internalTypeName = typeName;
                String objectRootElementName = ObjectPOJO.getObjectRootElementName(typeName);
                if (objectRootElementName != null) {
                    internalTypeName = objectRootElementName;
                }
                totalCount = getTypeItemCount(criteria, repository.getComplexType(internalTypeName), storage);
                itemPKResults.addAll(getTypeItems(criteria, repository.getComplexType(internalTypeName), storage, typeName));
            } else {
                // TMDM-4651: Returns type in correct dependency order.
                Collection<ComplexTypeMetadata> types = getClusterTypes(clusterName);
                int maxCount = criteria.getMaxItems();
                if (criteria.getSkip() < 0) { // MDM Studio may send negative values
                    criteria.setSkip(0);
                }
                List<String> currentInstanceResults;
                String objectRootElementName;
                for (ComplexTypeMetadata type : types) {
                    String internalTypeName = type.getName();
                    objectRootElementName = ObjectPOJO.getObjectRootElementName(internalTypeName);
                    if (objectRootElementName != null) {
                        internalTypeName = objectRootElementName;
                    }
                    int count = getTypeItemCount(criteria, repository.getComplexType(internalTypeName), storage);
                    totalCount += count;
                    if (itemPKResults.size() < maxCount) {
                        if (count > criteria.getSkip()) {
                            currentInstanceResults = getTypeItems(criteria, repository.getComplexType(internalTypeName), storage,
                                    type.getName());
                            int n = maxCount - itemPKResults.size();
                            if (n <= currentInstanceResults.size()) {
                                itemPKResults.addAll(currentInstanceResults.subList(0, n));
                            } else {
                                itemPKResults.addAll(currentInstanceResults);
                            }
                            criteria.setMaxItems(criteria.getMaxItems() - currentInstanceResults.size());
                            criteria.setSkip(0);
                        } else {
                            criteria.setSkip(criteria.getSkip() - count);
                        }
                    }
                }
            }
            itemPKResults.add(0, "<totalCount>" + totalCount + "</totalCount>"); //$NON-NLS-1$ //$NON-NLS-2$
        } finally {
            storage.commit();
        }
        return itemPKResults;
    }

    @Override
    protected Storage getStorage(String dataClusterName) {
        return storageAdmin.get(StorageAdmin.SYSTEM_STORAGE, StorageType.SYSTEM);
    }

    @Override
    public long deleteCluster(String clusterName) throws XmlServerException {
        return 0;
    }

    @Override
    public String[] getAllClusters() throws XmlServerException {
        Set<String> internalClusterNames = DispatchWrapper.getInternalClusterNames();
        return internalClusterNames.toArray(new String[internalClusterNames.size()]);
    }

    @Override
    public long deleteAllClusters() throws XmlServerException {
        return 0;
    }

    @Override
    public long createCluster(String clusterName) throws XmlServerException {
        return 0;
    }

    @Override
    public boolean existCluster(String cluster) throws XmlServerException {
        return true;
    }

    @Override
    protected Collection<ComplexTypeMetadata> getClusterTypes(String clusterName) {
        Storage storage = getStorage(clusterName);
        MetadataRepository repository = storage.getMetadataRepository();
        return filter(repository, clusterName);
    }

    public static Collection<ComplexTypeMetadata> filter(MetadataRepository repository, String clusterName) {
        if (clusterName.startsWith(SYSTEM_PREFIX) || clusterName.startsWith("amalto")) { //$NON-NLS-1$
            if (!"amaltoOBJECTSservices".equals(clusterName)) { //$NON-NLS-1$
                final String className = ClassRepository.format(clusterName.substring(SYSTEM_PREFIX.length()) + "POJO"); //$NON-NLS-1$
                return filterRepository(repository, className);
            } else {
                final String className = ClassRepository.format(clusterName.substring(SYSTEM_PREFIX.length()));
                return filterRepository(repository, className);
            }
        } else if (XSystemObjects.DC_MDMITEMSTRASH.getName().equals(clusterName)) {
            return filterRepository(repository, DROPPED_ITEM_TYPE);
        } else if (XSystemObjects.DC_CONF.getName().equals(clusterName)) {
            return filterRepository(repository, "Conf", "AutoIncrement"); //$NON-NLS-1$ //$NON-NLS-2$
        } else if (XSystemObjects.DC_CROSSREFERENCING.getName().equals(clusterName)) {
            return Collections.emptyList(); // TODO Support crossreferencing
        } else if (XSystemObjects.DC_PROVISIONING.getName().equals(clusterName)) {
            return filterRepository(repository, "User", "Role"); //$NON-NLS-1$ //$NON-NLS-2$
        } else if (XSystemObjects.DC_SEARCHTEMPLATE.getName().equals(clusterName)) {
            return filterRepository(repository, "BrowseItem", "HierarchySearchItem"); //$NON-NLS-1$ //$NON-NLS-2$
        } else {
            return repository.getUserComplexTypes();
        }
    }

    private static Collection<ComplexTypeMetadata> filterRepository(MetadataRepository repository, String... typeNames) {
        final Set<ComplexTypeMetadata> filteredTypes = new HashSet<ComplexTypeMetadata>();
        MetadataVisitor<Void> transitiveTypeClosure = new DefaultMetadataVisitor<Void>() {

            private final Set<TypeMetadata> visitedTypes = new HashSet<>();

            @Override
            public Void visit(ComplexTypeMetadata complexType) {
                if (!visitedTypes.add(complexType)) {
                    return null;
                }
                if (complexType.isInstantiable()) {
                    filteredTypes.add(complexType);
                }
                return super.visit(complexType);
            }

            @Override
            public Void visit(ContainedComplexTypeMetadata containedType) {
                if (!visitedTypes.add(containedType)) {
                    return null;
                }
                if (containedType.isInstantiable()) {
                    filteredTypes.add(containedType);
                }
                return super.visit(containedType);
            }
        };
        for (String typeName : typeNames) {
            ComplexTypeMetadata type = repository.getComplexType(typeName);
            if (type != null) {
                type.accept(transitiveTypeClosure);
            }
        }
        return filteredTypes;
    }

    @Override
    public String[] getAllDocumentsUniqueID(String clusterName) throws XmlServerException {
        String pureClusterName = getPureClusterName(clusterName);
        boolean includeClusterAndTypeName = getClusterTypes(pureClusterName).size() > 1;
        return getAllDocumentsUniqueID(clusterName, includeClusterAndTypeName, false);
    }

    @Override
    public String[] getAllDocumentsUniqueID(String clusterName, final boolean ignoreChild) throws XmlServerException {
        String pureClusterName = getPureClusterName(clusterName);
        boolean includeClusterAndTypeName = getClusterTypes(pureClusterName).size() > 1;
        return getAllDocumentsUniqueID(clusterName, includeClusterAndTypeName, ignoreChild);
    }

    @Override
    public long putDocumentFromDOM(Element root, String uniqueID, String clusterName) throws XmlServerException {
        long start = System.currentTimeMillis();
        {
            DataRecordReader<Element> reader = new XmlDOMDataRecordReader();
            Storage storage = getStorage(clusterName);
            ComplexTypeMetadata type = getType(clusterName, storage, uniqueID);
            if (type == null) {
                return -1; // TODO
            }
            MetadataRepository repository = storage.getMetadataRepository();
            DataRecord record = reader.read(repository, type, root);
            for (FieldMetadata keyField : type.getKeyFields()) {
                if (record.get(keyField) == null) {
                    LOGGER.warn("Ignoring update for record '" + uniqueID + "' (does not provide key information)."); //$NON-NLS-1$ //$NON-NLS-2$
                    return 0;
                }
            }
            storage.update(record);
        }
        return System.currentTimeMillis() - start;
    }

    @Override
    public long putDocumentFromSAX(String dataClusterName, XMLReader docReader, InputSource input) throws XmlServerException {
        long start = System.currentTimeMillis();
        {
            Storage storage = getStorage(dataClusterName);
            ComplexTypeMetadata type = getType(dataClusterName, storage, input.getPublicId());
            if (type == null) {
                return -1; // TODO
            }
            DataRecordReader<XmlSAXDataRecordReader.Input> reader = new XmlSAXDataRecordReader();
            XmlSAXDataRecordReader.Input readerInput = new XmlSAXDataRecordReader.Input(docReader, input);
            DataRecord record = reader.read(storage.getMetadataRepository(), type, readerInput);
            storage.update(record);
        }
        return System.currentTimeMillis() - start;
    }

    @Override
    public long putDocumentFromString(String xmlString, String uniqueID, String clusterName) throws XmlServerException {
        return putDocumentFromString(xmlString, uniqueID, clusterName, null);
    }

    @Override
    public long putDocumentFromString(String xmlString, String uniqueID, String clusterName, String documentType)
            throws XmlServerException {
        try {
            InputSource source = new InputSource(new StringReader(xmlString));
            Document document = MDMXMLUtils.getDocumentBuilderWithNamespace().get().parse(source);
            return putDocumentFromDOM(document.getDocumentElement(), uniqueID, clusterName);
        } catch (Exception e) {
            throw new XmlServerException(e);
        }
    }

    @Override
    public String getDocumentAsString(String clusterName, String uniqueID) throws XmlServerException {
        return getDocumentAsString(clusterName, uniqueID, "UTF-8"); //$NON-NLS-1$
    }

    @Override
    public String getDocumentAsString(String clusterName, String uniqueID, String encoding) throws XmlServerException {
        if (encoding == null) {
            encoding = "UTF-8"; //$NON-NLS-1$
        }
        Storage storage = getStorage(clusterName);
        ComplexTypeMetadata type = getType(clusterName, storage, uniqueID);
        if (type == null) {
            return null; // TODO
        }
        UserQueryBuilder qb;
        boolean isUserFormat = false;
        String documentUniqueID;
        if (DROPPED_ITEM_TYPE.equals(type.getName())) {
            // head.Product.Product.0- (but DM1.Bird.bid3)
            if (uniqueID.endsWith("-")) { //$NON-NLS-1$
                uniqueID = uniqueID.substring(0, uniqueID.length() - 1);
            }
            // TODO Code may not correctly handle composite id (but no system objects use this)
            documentUniqueID = uniqueID;
            if (StringUtils.countMatches(uniqueID, ".") >= 3) { //$NON-NLS-1$
                documentUniqueID = StringUtils.substringAfter(uniqueID, "."); //$NON-NLS-1$
            }
        } else if (COMPLETED_ROUTING_ORDER.equals(type.getName()) || FAILED_ROUTING_ORDER.equals(type.getName())) {
            documentUniqueID = uniqueID;
        } else {
            // TMDM-5513 custom form layout pk contains double dot .. to split, but it's a system definition object
            // like this Product..Product..product_layout
            isUserFormat = !uniqueID.contains("..") && uniqueID.indexOf('.') > 0; //$NON-NLS-1$
            documentUniqueID = uniqueID;
            if (uniqueID.startsWith(PROVISIONING_PREFIX_INFO)) {
                documentUniqueID = StringUtils.substringAfter(uniqueID, PROVISIONING_PREFIX_INFO);
            } else if (uniqueID.startsWith(BROWSEITEM_PREFIX_INFO)) {
                documentUniqueID = StringUtils.substringAfter(uniqueID, BROWSEITEM_PREFIX_INFO);
            } else if (isUserFormat) {
                documentUniqueID = StringUtils.substringAfterLast(uniqueID, "."); //$NON-NLS-1$
            }
        }
        qb = from(type).where(eq(type.getKeyFields().iterator().next(), documentUniqueID));
        StorageResults results = null;
        try {
            storage.begin();
            results = storage.fetch(qb.getSelect());
            String xmlString = getXmlString(clusterName, type, results.iterator(), uniqueID, encoding, isUserFormat);
            storage.commit();
            return xmlString;
        } catch (IOException e) {
            storage.rollback();
            throw new XmlServerException(e);
        } finally {
            if (results != null) {
                results.close();
            }
        }
    }

    @Override
    public long deleteDocument(String clusterName, String uniqueID, String documentType) throws XmlServerException {
        Storage storage = getStorage(clusterName);
        ComplexTypeMetadata type = getType(clusterName, storage, uniqueID);
        if (type == null) {
            return -1;
        }
        if (DROPPED_ITEM_TYPE.equals(type.getName())) {
            // head.Product.Product.0-
            uniqueID = uniqueID.substring(0, uniqueID.length() - 1);
            uniqueID = StringUtils.substringAfter(uniqueID, "."); //$NON-NLS-1$
        } else if (!COMPLETED_ROUTING_ORDER.equals(type.getName()) && !FAILED_ROUTING_ORDER.equals(type.getName())
                && !CUSTOM_FORM_TYPE.equals(type.getName()) && !SYNCHRONIZATION_OBJECT_TYPE.equals(type.getName())) {
            if (uniqueID.startsWith(PROVISIONING_PREFIX_INFO)) {
                uniqueID = StringUtils.substringAfter(uniqueID, PROVISIONING_PREFIX_INFO);
            } else if (uniqueID.startsWith(BROWSEITEM_PREFIX_INFO)) {
                uniqueID = StringUtils.substringAfter(uniqueID, BROWSEITEM_PREFIX_INFO);
            } else if (uniqueID.contains(".")) { //$NON-NLS-1$
                uniqueID = StringUtils.substringAfterLast(uniqueID, "."); //$NON-NLS-1$
            }
        }
        long start = System.currentTimeMillis();
        {
            UserQueryBuilder qb = from(type).where(eq(type.getKeyFields().iterator().next(), uniqueID));
            StorageResults results = null;
            try {
                storage.begin();
                Select select = qb.getSelect();
                results = storage.fetch(select);
                if (results.getCount() == 0) {
                    throw new IllegalArgumentException("Could not find document to delete."); //$NON-NLS-1$
                }
                storage.delete(select);
                storage.commit();
            } catch (Exception e) {
                storage.rollback();
                throw new XmlServerException(e);
            } finally {
                if (results != null) {
                    results.close();
                }
            }
        }
        return System.currentTimeMillis() - start;
    }

    @Override
    public String[] getDocumentsAsString(String clusterName, String[] uniqueIDs) throws XmlServerException {
        return getDocumentsAsString(clusterName, uniqueIDs, "UTF-8"); //$NON-NLS-1$
    }

    @Override
    public String[] getDocumentsAsString(String clusterName, String[] uniqueIDs, String encoding) throws XmlServerException {
        if (uniqueIDs == null || uniqueIDs.length == 0) {
            return new String[0];
        }
        List<String> xmlStrings = new ArrayList<String>(uniqueIDs.length);
        for (String uniqueID : uniqueIDs) {
            xmlStrings.add(getDocumentAsString(clusterName, uniqueID, encoding));
        }
        return xmlStrings.toArray(new String[xmlStrings.size()]);
    }
}
