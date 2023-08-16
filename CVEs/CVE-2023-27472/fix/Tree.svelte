<script lang="ts">
	import jQuery from "jquery"
	import "jstree"
	import "./treeview.css"

	import type { Entity, FullRef, Ref, RefWithConstantValue, SubEntity } from "$lib/quickentity-types"
	import { changeReferenceToLocalEntity, genRandHex, getReferencedEntities, getReferencedExternalEntities, getReferencedLocalEntity, normaliseToHash, sanitise, traverseEntityTree } from "$lib/utils"

	import { createEventDispatcher, onMount } from "svelte"
	import { v4 } from "uuid"
	import * as clipboard from "@tauri-apps/api/clipboard"
	import json from "$lib/json"
	import isEqual from "lodash/isEqual"
	import { gameServer } from "$lib/in-vivo/gameServer"
	import { addNotification, appSettings, intellisense, inVivoMetadata } from "$lib/stores"
	import { readTextFile } from "@tauri-apps/api/fs"
	import { join } from "@tauri-apps/api/path"

	export let entity: Entity
	export let reverseReferences: Record<
		string,
		{
			type: string
			entity: string
			context?: string[]
		}[]
	>
	export let inVivoExtensions: boolean

	export let currentlySelected: string = null!
	export let previouslySelected: string = null!
	export let editorIsValid: boolean
	export let autoHighlightEntities: boolean

	export const elemID = "tree-" + v4().replaceAll("-", "")

	export let tree: JSTree = null!

	export let helpMenuOpen: boolean = false
	export let helpMenuFactory: string = ""
	export let helpMenuProps: SubEntity["properties"] = {}
	export let helpMenuInputs: string[] = []
	export let helpMenuOutputs: string[] = []

	const dispatch = createEventDispatcher()

	const exists = async (path: string) => {
		try {
			return await tauriExists(path)
		} catch {
			return false
		}
	}

	const icons = Object.entries({
		"[assembly:/templates/gameplay/ai2/actors.template?/npcactor.entitytemplate].pc_entitytype": "far fa-user",
		"[assembly:/_pro/characters/templates/hero/agent47/agent47.template?/agent47_default.entitytemplate].pc_entitytype": "far fa-user-circle",
		"[assembly:/_pro/design/levelflow.template?/herospawn.entitytemplate].pc_entitytype": "far fa-user-circle",
		"[modules:/zglobaloutfitkit.class].pc_entitytype": "fas fa-tshirt",
		"[modules:/zroomentity.class].pc_entitytype": "fas fa-map-marker-alt",
		"[modules:/zboxvolumeentity.class].pc_entitytype": "far fa-square",
		"[modules:/zsoundbankentity.class].pc_entitytype": "fas fa-music",
		"[modules:/zcameraentity.class].pc_entitytype": "fas fa-camera",
		"[modules:/zsequenceentity.class].pc_entitytype": "fas fa-film",
		"[modules:/zhitmandamageovertime.class].pc_entitytype": "fas fa-skull-crossbones",
		"0059FBD4AEBCDED0": "far fa-comment", // Hashes

		"levelflow.template?/exit": "fas fa-sign-out-alt",
		zitem: "fas fa-wrench", // Specific

		blockup: "fas fa-cube",
		setpiece_container_body: "fas fa-box-open",
		setpiece_trap: "fas fa-skull-crossbones",
		animset: "fas fa-running",
		emitter: "fas fa-wifi",
		sender: "fas fa-wifi",
		event: "fas fa-location-arrow",
		death: "fas fa-skull",
		zone: "far fa-square", // Types

		"foliage/": "fas fa-seedling",
		"vehicles/": "fas fa-car-side",
		"environment/": "far fa-map",
		"logic/": "fas fa-cogs",
		"design/": "fas fa-swatchbook",
		"modules:/": "fas fa-project-diagram" // Paths
	})

	onMount(async () => {
		jQuery("#" + elemID).jstree({
			core: {
				multiple: false,
				data: [],
				themes: {
					name: "default",
					dots: true,
					icons: true
				},
				check_callback: true,
				force_text: true
			},
			search: {
				fuzzy: true,
				show_only_matches: true,
				close_opened_onclear: false,
				search_callback: (search: string, node: { id: string }) => {
					if (search.startsWith(":")) {
						if (entity.entities[node.id]) {
							return eval(search.slice(1))({ ...entity.entities[node.id], id: node.id })
						}
					} else {
						return (json.stringify(entity.entities[node.id] || entity.comments[Number(node.id.split("-")[1])]) + node.id).toLowerCase().includes(search)
					}
				}
			},
			sort: function (a: any, b: any) {
				if (
					(!(this.get_node(a).original ? this.get_node(a).original : this.get_node(a)).folder && !(this.get_node(b).original ? this.get_node(b).original : this.get_node(b)).folder) ||
					((this.get_node(a).original ? this.get_node(a).original : this.get_node(a)).folder && (this.get_node(b).original ? this.get_node(b).original : this.get_node(b)).folder)
				) {
					return this.get_text(a).localeCompare(this.get_text(b), undefined, { numeric: true, sensitivity: "base" }) > 0 ? 1 : -1
				} else {
					return (this.get_node(a).original ? this.get_node(a).original : this.get_node(a)).folder ? -1 : 1
				}
			},
			contextmenu: {
				select_node: false,
				items: (b: { id: string }, c: any) => {
					return {
						create: {
							separator_before: false,
							separator_after: true,
							_disabled: false,
							label: "Create",
							icon: "fas fa-plus",
							action: function (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) {
								var c = jQuery.jstree!.reference(b.reference),
									d = c.get_node(b.reference)
								c.create_node(d, {}, "last", function (a: any) {
									try {
										c.edit(a)
									} catch (b) {
										setTimeout(function () {
											c.edit(a)
										}, 0)
									}
								})
							}
						},
						createComment: {
							separator_before: false,
							separator_after: true,
							_disabled: false,
							label: "Add Comment",
							icon: "far fa-sticky-note",
							action: function (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) {
								entity.comments = [
									...entity.comments,
									{
										parent: jQuery.jstree!.reference(b.reference).get_node(b.reference).id,
										name: "New Comment",
										text: ""
									}
								]
							}
						},
						rename: {
							separator_before: false,
							separator_after: false,
							_disabled: false,
							label: "Rename",
							icon: "far fa-pen-to-square",
							action: function (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) {
								var c = jQuery.jstree!.reference(b.reference),
									d = c.get_node(b.reference)
								c.edit(d)
							}
						},
						remove: {
							separator_before: false,
							separator_after: false,
							_disabled: false,
							label: "Delete",
							icon: "far fa-trash-can",
							action: function (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) {
								var c = jQuery.jstree!.reference(b.reference),
									d = c.get_node(b.reference)
								c.is_selected(d) ? c.delete_node(c.get_selected()) : c.delete_node(d)
							}
						},
						...(!inVivoExtensions || b.id.startsWith("comment") || !gameServer.connected || !gameServer.lastAddress
							? {}
							: {
									inVivo: {
										separator_before: true,
										separator_after: false,
										label: "In-Vivo",
										icon: "fas fa-right-left",
										action: false,
										submenu: {
											highlight: {
												separator_before: false,
												separator_after: false,
												label: "Highlight",
												icon: "fas fa-highlighter",
												action: async (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) => {
													let d = tree.get_node(b.reference)

													await gameServer.highlightEntity(d.id, entity.entities[d.id])

													$addNotification = {
														kind: "success",
														title: "Entity highlighted",
														subtitle: "Check your game; the entity should now be displaying its bounding box."
													}
												}
											},
											moveToPlayerPosition: {
												separator_before: false,
												separator_after: false,
												label: "Move to Player Position",
												icon: "fas fa-location-dot",
												action: async (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) => {
													let d = tree.get_node(b.reference)

													let playerPos = await gameServer.getPlayerPosition()

													entity.entities[d.id].properties ??= {}
													entity.entities[d.id].properties!.m_mTransform ??= {
														type: "SMatrix43",
														value: {
															rotation: {
																x: 0,
																y: 0,
																z: 0
															},
															position: {
																x: 0,
																y: 0,
																z: 0
															}
														}
													}

													if (entity.entities[d.id].properties!.m_eidParent) {
														entity.entities[d.id].properties = Object.fromEntries(Object.entries(entity.entities[d.id].properties!).filter((a) => a[0] != "m_eidParent"))

														// TODO: this isn't always going to work so it should probably be hooked up to intellisense in case of aliases or such
														entity.entities[d.id].properties!.m_eRoomBehaviour = {
															type: "ZSpatialEntity.ERoomBehaviour",
															value: "ROOM_DYNAMIC"
														}
													}

													entity.entities[d.id].properties!.m_mTransform.value.position.x = playerPos.x
													entity.entities[d.id].properties!.m_mTransform.value.position.y = playerPos.y
													entity.entities[d.id].properties!.m_mTransform.value.position.z = playerPos.z

													dispatch("entityUpdated", d.id)

													await gameServer.updateProperty(d.id, "m_mTransform", entity.entities[d.id].properties!.m_mTransform)

													$inVivoMetadata.entities[d.id] ??= {
														dirtyPins: false,
														dirtyUnchangeables: false,
														dirtyExtensions: false,
														dirtyProperties: [],
														hasSetProperties: false
													}

													$inVivoMetadata.entities[d.id].dirtyProperties = $inVivoMetadata.entities[d.id].dirtyProperties.filter((a) => a != "m_mTransform")

													$addNotification = {
														kind: "success",
														title: "Entity set to player position",
														subtitle: "The m_mTransform property has been updated accordingly."
													}
												}
											},
											adjustRotationToPlayer: {
												separator_before: false,
												separator_after: false,
												label: "Adjust Rotation to Player",
												icon: "fas fa-compass",
												action: async (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) => {
													let d = tree.get_node(b.reference)

													let playerRot = await gameServer.getPlayerRotation()

													entity.entities[d.id].properties ??= {}
													entity.entities[d.id].properties!.m_mTransform ??= {
														type: "SMatrix43",
														value: {
															rotation: {
																x: 0,
																y: 0,
																z: 0
															},
															position: {
																x: 0,
																y: 0,
																z: 0
															}
														}
													}

													entity.entities[d.id].properties!.m_mTransform.value.rotation.x = playerRot.x
													entity.entities[d.id].properties!.m_mTransform.value.rotation.y = playerRot.y
													entity.entities[d.id].properties!.m_mTransform.value.rotation.z = playerRot.z

													dispatch("entityUpdated", d.id)

													await gameServer.updateProperty(d.id, "m_mTransform", entity.entities[d.id].properties!.m_mTransform)

													$inVivoMetadata.entities[d.id] ??= {
														dirtyPins: false,
														dirtyUnchangeables: false,
														dirtyExtensions: false,
														dirtyProperties: [],
														hasSetProperties: false
													}

													$inVivoMetadata.entities[d.id].dirtyProperties = $inVivoMetadata.entities[d.id].dirtyProperties.filter((a) => a != "m_mTransform")

													$addNotification = {
														kind: "success",
														title: "Entity set to player rotation",
														subtitle: "The m_mTransform property has been updated accordingly."
													}
												}
											}
										}
									}
							  }),
						ccp: {
							separator_before: true,
							separator_after: false,
							label: "Clipboard",
							icon: "far fa-clipboard",
							action: false,
							submenu: {
								copy: {
									separator_before: false,
									separator_after: false,
									label: "Copy Entity",
									icon: "far fa-copy",
									action: async (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) => {
										let d = tree.get_node(b.reference)
										let copiedEntity: Record<string, any> = {}

										copiedEntity[d.id] = json.parse(json.stringify(entity.entities[d.id]))
										Object.assign(
											copiedEntity,
											json.parse(json.stringify(Object.fromEntries([...new Set(traverseEntityTree(entity, d.id, reverseReferences))].map((a) => [a, entity.entities[a]]))))
										)

										copiedEntity.origin = entity.tempHash

										await clipboard.writeText(json.stringify(copiedEntity))
									}
								},
								paste: {
									separator_before: false,
									_disabled: false,
									separator_after: false,
									label: "Paste Entity",
									icon: "far fa-paste",
									action: async (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) => {
										let d = tree.get_node(b.reference)
										let pastedEntity = json.parse((await clipboard.readText())!)

										let removeExternalRefs = pastedEntity.origin != entity.tempHash
										delete pastedEntity.origin

										let changedEntityIDs: Record<string, string> = {}
										for (let ent of Object.entries(pastedEntity)) {
											changedEntityIDs[ent[0]] = "feed" + genRandHex(12)

											pastedEntity[changedEntityIDs[ent[0]]] = ent[1]
											delete pastedEntity[ent[0]]
										}

										let paste: Record<string, SubEntity> = pastedEntity

										for (let [entID, ent] of Object.entries(paste)) {
											const localRef = getReferencedLocalEntity(ent.parent)
											ent.parent = localRef && changedEntityIDs[localRef] ? changeReferenceToLocalEntity(ent.parent, changedEntityIDs[localRef]) : ent.parent

											for (let ref of getReferencedEntities(ent)) {
												if (changedEntityIDs[ref.entity]) {
													switch (ref.type) {
														case "property":
															if (Array.isArray(ent.properties![ref.context![0]].value)) {
																ent.properties![ref.context![0]].value.splice(
																	ent.properties![ref.context![0]].value.findIndex((a: Ref) => getReferencedLocalEntity(a) == ref.entity),
																	1,
																	changeReferenceToLocalEntity(
																		ent.properties![ref.context![0]].value.find((a: Ref) => getReferencedLocalEntity(a) == ref.entity),
																		changedEntityIDs[ref.entity]
																	)
																)
															} else {
																ent.properties![ref.context![0]].value = changeReferenceToLocalEntity(
																	ent.properties![ref.context![0]].value,
																	changedEntityIDs[ref.entity]
																)
															}
															break

														case "platformSpecificProperty":
															if (Array.isArray(ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value)) {
																ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value.splice(
																	ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value.findIndex(
																		(a: Ref) => getReferencedLocalEntity(a) == ref.entity
																	),
																	1,
																	changeReferenceToLocalEntity(
																		ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value.find(
																			(a: Ref) => getReferencedLocalEntity(a) == ref.entity
																		),
																		changedEntityIDs[ref.entity]
																	)
																)
															} else {
																ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value = changeReferenceToLocalEntity(
																	ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value,
																	changedEntityIDs[ref.entity]
																)
															}
															break

														case "event":
															let evtIndex = ent.events![ref.context![0]][ref.context![1]].findIndex(
																(a) =>
																	getReferencedLocalEntity(a && typeof a != "string" && Object.prototype.hasOwnProperty.call(a, "value") ? a.ref : (a as FullRef)) ==
																	ref.entity
															)

															let evt = ent.events![ref.context![0]][ref.context![1]][evtIndex]

															ent.events![ref.context![0]][ref.context![1]].splice(
																evtIndex,
																1,
																evt && typeof evt != "string" && Object.prototype.hasOwnProperty.call(evt, "value")
																	? {
																			ref: changeReferenceToLocalEntity(evt.ref, changedEntityIDs[ref.entity]),
																			value: (evt as RefWithConstantValue).value
																	  }
																	: changeReferenceToLocalEntity(evt as FullRef, changedEntityIDs[ref.entity])
															)
															break

														case "inputCopy":
															let evt2Index = ent.inputCopying![ref.context![0]][ref.context![1]].findIndex(
																(a) =>
																	getReferencedLocalEntity(a && typeof a != "string" && Object.prototype.hasOwnProperty.call(a, "value") ? a.ref : (a as FullRef)) ==
																	ref.entity
															)

															let evt2 = ent.inputCopying![ref.context![0]][ref.context![1]][evt2Index]

															ent.inputCopying![ref.context![0]][ref.context![1]].splice(
																evt2Index,
																1,
																evt2 && typeof evt2 != "string" && Object.prototype.hasOwnProperty.call(evt2, "value")
																	? {
																			ref: changeReferenceToLocalEntity(evt2.ref, changedEntityIDs[ref.entity]),
																			value: (evt2 as RefWithConstantValue).value
																	  }
																	: changeReferenceToLocalEntity(evt2 as FullRef, changedEntityIDs[ref.entity])
															)
															break

														case "outputCopy":
															let evt3Index = ent.outputCopying![ref.context![0]][ref.context![1]].findIndex(
																(a) =>
																	getReferencedLocalEntity(a && typeof a != "string" && Object.prototype.hasOwnProperty.call(a, "value") ? a.ref : (a as FullRef)) ==
																	ref.entity
															)

															let evt3 = ent.outputCopying![ref.context![0]][ref.context![1]][evt3Index]

															ent.outputCopying![ref.context![0]][ref.context![1]].splice(
																evt3Index,
																1,
																evt3 && typeof evt3 != "string" && Object.prototype.hasOwnProperty.call(evt3, "value")
																	? {
																			ref: changeReferenceToLocalEntity(evt3.ref, changedEntityIDs[ref.entity]),
																			value: (evt3 as RefWithConstantValue).value
																	  }
																	: changeReferenceToLocalEntity(evt3 as FullRef, changedEntityIDs[ref.entity])
															)
															break

														case "propertyAlias":
															ent.propertyAliases![ref.context![0]].splice(
																ent.propertyAliases![ref.context![0]].findIndex((a) => isEqual(a, { originalProperty: ref.context![1], originalEntity: ref.entity })),
																1,
																Object.assign(
																	ent.propertyAliases![ref.context![0]].find((a) => isEqual(a, { originalProperty: ref.context![1], originalEntity: ref.entity }))!,
																	{
																		originalEntity: changedEntityIDs[ref.entity]
																	}
																)
															)
															break

														case "exposedEntity":
															ent.exposedEntities![ref.context![0]].refersTo.splice(
																ent.exposedEntities![ref.context![0]].refersTo.findIndex((a) => getReferencedLocalEntity(a) == ref.entity),
																1,
																changeReferenceToLocalEntity(
																	ent.exposedEntities![ref.context![0]].refersTo.find((a) => getReferencedLocalEntity(a) == ref.entity)!,
																	changedEntityIDs[ref.entity]
																)
															)
															break

														case "exposedInterface":
															ent.exposedInterfaces![ref.context![0]] = changedEntityIDs[ref.entity]
															break

														case "subset":
															ent.subsets![ref.context![0]].splice(
																ent.subsets![ref.context![0]].findIndex((a) => getReferencedLocalEntity(a) == ref.entity),
																1,
																changedEntityIDs[ref.entity]
															)
															break
													}
												}
											}

											if (removeExternalRefs) {
												for (let ref of getReferencedExternalEntities(ent, paste)) {
													let localRef = getReferencedLocalEntity(ref.entity)
													if (!localRef || !entity.entities[localRef]) {
														switch (ref.type) {
															case "property":
																if (Array.isArray(ent.properties![ref.context![0]].value)) {
																	ent.properties![ref.context![0]].value = ent.properties![ref.context![0]].value.filter((a: Ref) => !isEqual(a, ref.entity))
																} else {
																	delete ent.properties![ref.context![0]]
																}
																break

															case "platformSpecificProperty":
																if (Array.isArray(ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value)) {
																	ent.platformSpecificProperties![ref.context![0]][ref.context![1]].value = ent.platformSpecificProperties![ref.context![0]][
																		ref.context![1]
																	].value.filter((a: Ref) => !isEqual(a, ref.entity))
																} else {
																	delete ent.platformSpecificProperties![ref.context![0]][ref.context![1]]
																}
																break

															case "event":
																ent.events![ref.context![0]][ref.context![1]] = ent.events![ref.context![0]][ref.context![1]].filter(
																	(a) => !isEqual(a && typeof a != "string" && Object.prototype.hasOwnProperty.call(a, "value") ? a.ref : (a as FullRef), ref.entity)
																)
																break

															case "inputCopy":
																ent.inputCopying![ref.context![0]][ref.context![1]] = ent.inputCopying![ref.context![0]][ref.context![1]].filter(
																	(a) => !isEqual(a && typeof a != "string" && Object.prototype.hasOwnProperty.call(a, "value") ? a.ref : (a as FullRef), ref.entity)
																)
																break

															case "outputCopy":
																ent.outputCopying![ref.context![0]][ref.context![1]] = ent.outputCopying![ref.context![0]][ref.context![1]].filter(
																	(a) => !isEqual(a && typeof a != "string" && Object.prototype.hasOwnProperty.call(a, "value") ? a.ref : (a as FullRef), ref.entity)
																)
																break

															case "propertyAlias":
																ent.propertyAliases![ref.context![0]] = ent.propertyAliases![ref.context![0]].filter(
																	(a) => !isEqual(a, { originalProperty: ref.context![1], originalEntity: ref.entity })
																)
																break

															case "exposedEntity":
																ent.exposedEntities![ref.context![0]].refersTo = ent.exposedEntities![ref.context![0]].refersTo.filter((a) => !isEqual(a, ref.entity))
																break

															case "exposedInterface":
																delete ent.exposedInterfaces![ref.context![0]]
																break

															case "subset":
																ent.subsets![ref.context![0]] = ent.subsets![ref.context![0]].filter((a) => a != ref.entity)
																break
														}
													}
												}
											}
										}

										Object.assign(entity.entities, paste)

										entity.entities[Object.keys(paste)[0]].parent = changeReferenceToLocalEntity(entity.entities[Object.keys(paste)[0]].parent, d.id)

										dispatch("forceUpdateEntity")
									}
								}
							}
						},
						copyID: {
							separator_before: false,
							separator_after: false,
							_disabled: false,
							label: "Copy ID",
							icon: "far fa-copy",
							action: function (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) {
								let d = tree.get_node(b.reference)

								clipboard.writeText(d.id)
							}
						},
						...(!currentlySelected || !$appSettings.gameFileExtensions || b.id.startsWith("comment")
							? {}
							: {
									help: {
										separator_before: false,
										separator_after: false,
										_disabled: false,
										label: "Help",
										icon: "far fa-circle-question",
										action: async function (b: { reference: string | HTMLElement | JQuery<HTMLElement> }) {
											let d = tree.get_node(b.reference)

											let entityID = d.id
											let entityData = entity.entities[d.id]

											helpMenuFactory = entityData.factory
											helpMenuProps = {}
											helpMenuInputs = []
											helpMenuOutputs = []

											let allFoundProperties = []

											for (let factory of (await exists(await join($appSettings.gameFileExtensionsDataPath, "ASET", normaliseToHash(entityData.factory) + ".ASET.meta.JSON")))
												? json
														.parse(await readTextFile(await join($appSettings.gameFileExtensionsDataPath, "ASET", normaliseToHash(entityData.factory) + ".ASET.meta.JSON")))
														.hash_reference_data.slice(0, -1)
														.map((a) => a.hash)
												: [normaliseToHash(entityData.factory)]) {
												if (await exists(await join($appSettings.gameFileExtensionsDataPath, "TEMP", factory + ".TEMP.entity.json"))) {
													await $intellisense.findProperties(await join($appSettings.gameFileExtensionsDataPath, "TEMP", factory + ".TEMP.entity.json"), allFoundProperties)
													entityData.propertyAliases && allFoundProperties.push(...Object.keys(entityData.propertyAliases))
												} else if ($intellisense.knownCPPTProperties[factory]) {
													allFoundProperties.push(...Object.keys($intellisense.knownCPPTProperties[factory]))
												} else if ($intellisense.allUICTs.has(factory)) {
													allFoundProperties.push(...Object.keys($intellisense.knownCPPTProperties["002C4526CC9753E6"])) // All UI controls have the properties of ZUIControlEntity
													allFoundProperties.push(
														...Object.keys(
															json.parse(
																await readTextFile(
																	await join(
																		"./intellisense-data/UICB",
																		json
																			.parse(await readTextFile(await join($appSettings.gameFileExtensionsDataPath, "UICT", factory + ".UICT.meta.JSON")))
																			.hash_reference_data.filter((a) => a.hash != "002C4526CC9753E6")[0].hash + ".UICB.json"
																	)
																)
															).properties
														)
													) // Get the specific properties from the UICB
												}
											}

											allFoundProperties = [...new Set(allFoundProperties)]

											helpMenuProps = {}

											if ($intellisense.knownCPPTProperties[normaliseToHash(entityData.factory)]) {
												for (let foundProp of allFoundProperties) {
													helpMenuProps[foundProp] = {
														type: $intellisense.knownCPPTProperties[normaliseToHash(entityData.factory)][foundProp][0],
														value: $intellisense.knownCPPTProperties[normaliseToHash(entityData.factory)][foundProp][1]
													}
												}
											} else {
												for (let foundProp of allFoundProperties) {
													let val = await $intellisense.findDefaultPropertyValue(entity.tempHash + ".TEMP.entity.json", entityID, foundProp, entity, entityID)

													if (val) {
														helpMenuProps[foundProp] = val
													}
												}
											}

											let pins = { input: [], output: [] }
											await $intellisense.getPins(entity, entityID, true, pins)
											helpMenuInputs = [...new Set(pins.input)]
											helpMenuOutputs = [...new Set(pins.output)]

											helpMenuOpen = true
										}
									}
							  })
					}
				}
			},
			plugins: ["contextmenu", "dnd", "search", "sort"]
		})

		tree = jQuery("#" + elemID).jstree()

		jQuery("#" + elemID).on("changed.jstree", (...data) => {
			if (data[1].action == "select_node" && data[1].node.id != currentlySelected) {
				if (editorIsValid) {
					previouslySelected = currentlySelected
					currentlySelected = data[1].node.id

					if (inVivoExtensions && autoHighlightEntities && gameServer.connected && gameServer.lastAddress && !data[1].node.id.startsWith("comment")) {
						gameServer.highlightEntity(data[1].node.id, entity.entities[data[1].node.id])
					}

					dispatch("selectionUpdate", data)
				} else {
					tree.deselect_node(data[1].node.id)
					tree.select_node(currentlySelected)
				}
			}
		})
		jQuery("#" + elemID).on("move_node.jstree", (...data) => dispatch("dragAndDrop", data))
		jQuery("#" + elemID).on("create_node.jstree", (...data) => dispatch("nodeCreated", data))
		jQuery("#" + elemID).on("rename_node.jstree", (...data) => dispatch("nodeRenamed", data))
		jQuery("#" + elemID).on("delete_node.jstree", (...data) => dispatch("nodeDeleted", data))
	})

	export function refreshTree(
		entity: Entity,
		reverseReferences: Record<
			string,
			{
				type: string
				entity: string
				context?: string[]
			}[]
		>
	) {
		tree.settings!.core.data = []

		for (let [entityID, entityData] of Object.entries(entity.entities)) {
			tree.settings!.core.data.push({
				id: String(entityID),
				parent: getReferencedLocalEntity(entityData.parent) || "#",
				icon:
					entityData.factory == "[modules:/zentity.class].pc_entitytype" && reverseReferences[entityID].some((a) => a.type == "parent")
						? "far fa-folder"
						: icons.find((a) => entityData.factory.includes(a[0]))
						? icons.find((a) => entityData.factory.includes(a[0]))![1]
						: "far fa-file",
				text: `${sanitise(entityData.name)} (ref ${entityID})`,
				folder: entityData.factory == "[modules:/zentity.class].pc_entitytype" && reverseReferences[entityID].some((a) => a.type == "parent") // for sorting and stuff
			})
		}

		let index = 0
		for (let entry of entity.comments) {
			tree.settings!.core.data.push({
				id: "comment-" + index,
				parent: getReferencedLocalEntity(entry.parent) || "#",
				icon: "far fa-sticky-note",
				text: sanitise(entry.name) + " (comment)",
				folder: false // for sorting and stuff
			})

			index++
		}

		setTimeout(() => {
			try {
				tree.get_node(currentlySelected, true)[0].scrollIntoView()
			} catch {}
		}, 100)

		tree.refresh()
	}

	let oldEntityNames: string[] = []
	let oldComments = ""
	let oldEntityCount = 0

	$: if (tree) {
		if (
			!isEqual(
				Object.values(entity.entities).map((a) => a.name),
				oldEntityNames
			) ||
			Object.keys(entity.entities).length != oldEntityCount ||
			entity.comments.map((a) => a.parent + a.name).join("") != oldComments
		) {
			if (Object.keys(entity.entities).every((entityID) => reverseReferences[entityID])) {
				refreshTree(entity, reverseReferences)
				oldEntityNames = Object.values(entity.entities).map((a) => a.name)
				oldEntityCount = Object.keys(entity.entities).length
				oldComments = entity.comments.map((a) => a.parent + a.name).join("")
			}
		}
	}

	export function search(query: string) {
		tree.search(query.toLowerCase())
	}

	export function navigateTo(ent: string) {
		tree.deselect_node(currentlySelected)
		tree.select_node(ent)
	}

	export function deselect() {
		tree.deselect_all()
		currentlySelected = null!
	}

	export function getMatching(search: string) {
		return tree.settings!.core.data.filter((node) => {
			if (search.startsWith(":")) {
				if (entity.entities[node.id]) {
					return eval(search.slice(1))(entity.entities[node.id])
				}
			} else {
				return (json.stringify(entity.entities[node.id] || entity.comments[Number(node.id.split("-")[1])]) + node.id).toLowerCase().includes(search)
			}
		})
	}
</script>

<div id={elemID} />
