<script lang="ts">
	import jQuery from "jquery"
	import "jstree"
	import "./treeview.css"

	import type { Entity } from "$lib/quickentity-types"
	import { getReferencedLocalEntity, sanitise } from "$lib/utils"

	import { createEventDispatcher, onMount } from "svelte"
	import { v4 } from "uuid"
	import json from "$lib/json"
	import isEqual from "lodash/isEqual"

	export let entity: Entity
	export let reverseReferences: Record<
		string,
		{
			type: string
			entity: string
			context?: string[]
		}[]
	>

	export let currentlySelected: string = null!

	export const elemID = "tree-" + v4().replaceAll("-", "")

	export let tree: JSTree = null!

	const dispatch = createEventDispatcher()

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
						}
					}
				}
			},
			plugins: ["contextmenu", "search", "sort"]
		})

		tree = jQuery("#" + elemID).jstree()

		jQuery("#" + elemID).on("changed.jstree", (...data) => {
			if (data[1].action == "select_node" && data[1].node.id != currentlySelected) {
				currentlySelected = data[1].node.id
				dispatch("selectionUpdate", data)
			}
		})
		jQuery("#" + elemID).on("rename_node.jstree", (...data) => dispatch("nodeRenamed", data))
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
</script>

<div id={elemID} />
