import type {Key} from "@aedart/contracts/support";
import type {
    Context,
    MetaCallback,
    MetaEntry,
    MetadataRecord,
    MetaTargetContext
} from "@aedart/contracts/support/meta";
import { METADATA } from "@aedart/contracts/support/meta";
import { set, get } from "@aedart/support/objects";
import { cloneDeep } from "lodash-es";

/**
 * Registry that contains the writable metadata (`context.metadata`).
 * 
 * **Warning**: _This registry is **NOT intended** to be available for writing,
 * outside the scope of the meta decorator._
 *
 * @type {WeakMap<object, MetadataRecord>}
 */
const registry: WeakMap<object, MetadataRecord> = new WeakMap<object, MetadataRecord>();

/**
 * Store value as metadata, for given key.
 *
 * @see getMeta
 * @see getAllMeta
 *
 * @param {Key | MetaCallback} key Key or path identifier. If callback is given,
 *                                 then its resulting {@link MetaEntry}'s `key`
 *                                 and `value` are stored.
 * @param {unknown} [value] Value to store. Ignored if `key` argument is
 *                          a callback.
 * @returns {(target: object, context: Context) => (void | ((initialValue: unknown) => unknown) | undefined)}
 */
export function meta(
    key: Key | MetaCallback,
    value?: unknown
) {
    return (target: object, context: Context) => {

        switch(context.kind) {
            // For a class target, the meta can be added directly.
            case 'class':
                return save(
                    resolveMetaTargetContext(target, target, context),
                    context,
                    key,
                    value
                );

            // When a field is decorated, we need to rely on the value initialisation to
            // obtain correct owner...
            case 'field':
                return function(initialValue: unknown) {
                    save(
                        // @ts-expect-error: "this" corresponds to class instance.
                        resolveMetaTargetContext(target, this, context),
                        context,
                        key,
                        value
                    );

                    return initialValue;
                }

            // For all other kinds of targets, we need to use the initialisation logic
            // to obtain the correct owner. This is needed for current implementation
            // and until the TC39 proposal is approved and implemented.
            // @see https://github.com/tc39/proposal-decorator-metadata
            default:
                context.addInitializer(function() {
                    save(
                        resolveMetaTargetContext(target, this, context),
                        context,
                        key,
                        value
                    );
                });
                return;
        }
    }
}

/**
 * Return metadata that matches key, for given target
 *
 * @see getAllMeta
 *
 * @template T
 * @template D=unknown Type of default value
 *
 * @param {object} owner Class that owns metadata
 * @param {Key} key Key or path identifier
 * @param {D} [defaultValue=undefined] Default value to return, in case key does not exist
 *
 * @returns {T | D | undefined}
 */
export function getMeta<T, D = unknown>(owner: object, key: Key, defaultValue?: D): T | D | undefined
{
    const metadata: Readonly<MetadataRecord> | undefined = getAllMeta(owner);
    if (metadata === undefined) {
        return defaultValue;
    }
    
    return get(metadata, key, defaultValue);
}

/**
 * Returns all registered metadata for given target, if available
 *
 * @see getMeta
 *
 * @param {object} owner Class that owns metadata
 *
 * @returns {Readonly<MetadataRecord> | undefined}
 */
export function getAllMeta(owner: object): Readonly<MetadataRecord> | undefined
{
    // @ts-expect-error: Owner can have Symbol.metadata defined - or not
    return owner[METADATA] ?? undefined;
}

/**
 * Save metadata
 * 
 * @param {MetaTargetContext} targetContext
 * @param {Context} context Decorator context
 * @param {Key | MetaCallback} key Key or path identifier. If callback is given,
 *                                 then its resulting {@link MetaEntry}'s `key`
 *                                 and `value` are stored.
 * @param {unknown} [value] Value to store. Ignored if `key` argument is
 *                          a callback.
 *
 * @return {void}
 */
function save(
    targetContext: MetaTargetContext,
    context: Context,
    key: Key | MetaCallback,
    value?: unknown,
)
{
    // Determine if metadata from context can be used (if it's available), and resolve it either from
    // the decorator context or from the registry.
    const useMetaFromContext: boolean = Reflect.has(context, 'metadata') && typeof context.metadata === 'object';
    const metadata: MetadataRecord = resolveMetadataRecord(targetContext.owner, context, useMetaFromContext);

    // Set context.metadata, in case that it didn't exist in the decorator context, when
    // reaching this point. This also allows "meta callback" to access previous defined
    // metadata.
    context.metadata = metadata;

    // Whenever the key is a "meta" callback, for any other kind than a class or a field,
    // we overwrite the "context.addInitializer" method, so init callbacks can be invoked
    // manually after meta has been defined.
    const initCallbacks: ((this: any /* eslint-disable-line @typescript-eslint/no-explicit-any */) => void)[] = [];
    if (typeof key === 'function' && (context.kind !== 'class' && context.kind !== 'field')) {
        context.addInitializer = (callback: (this: any /* eslint-disable-line @typescript-eslint/no-explicit-any */) => void) => {
            initCallbacks.push(callback);
        }
    }

    // Resolve meta entry (key and value). When a "meta callback" is given, it is invoked
    // here. Afterward, set the resolved key-value. 
    const entry: MetaEntry = resolveEntry(
        targetContext,
        context,
        key,
        value,
    );

    set(metadata, entry.key, entry.value);
    
    // When the metadata originates from the decorator context, we can stop here.
    // Otherwise, we need to save it in the internal registry...
    if (useMetaFromContext) {
        runIniCallbacks(targetContext, initCallbacks);
        return;
    }

    registry.set(targetContext.owner, metadata);

    // Lastly, define the owner[Symbol.metadata] property (only done once for the owner).
    // In case that owner is a subclass, then this ensures that it "overwrites" the parent's
    // [Symbol.metadata] property and offers its own version thereof.
    Reflect.defineProperty(targetContext.owner, METADATA, {
        get: () => {
            // To ensure that metadata cannot be changed outside the scope and context of a
            // meta decorator, a deep clone of the record is returned here. JavaScript's
            // native structuredClone cannot be used, because it does not support symbols.
            return cloneDeep(registry.get(targetContext.owner));
        },
        
        // Ensure that the property cannot be deleted
        configurable: false
    });

    // Invoke evt. init callbacks...
    runIniCallbacks(targetContext, initCallbacks);
}

/**
 * Resolve the metadata record that must be used when writing new metadata
 * 
 * @param {object} owner
 * @param {Context} context
 * @param {boolean} useMetaFromContext
 * 
 * @returns {MetadataRecord}
 */
function resolveMetadataRecord(owner: object, context: Context, useMetaFromContext: boolean): MetadataRecord
{
    // If registry is not to be used, it means that context.metadata is available 
    if (useMetaFromContext) {
        return context.metadata as MetadataRecord;
    }

    // Obtain record from registry, or create new empty object.
    let metadata: MetadataRecord = registry.get(owner) ?? Object.create(null);

    // In case that the owner has Symbol.metadata defined (e.g. from base class),
    // then merge it current metadata. This ensures that inheritance works as
    // intended, whilst a base class still keeping its original metadata.
    if (Reflect.has(owner, METADATA)) {
        // @ts-expect-error: Owner has Symbol.metadata!
        metadata = Object.assign(metadata, owner[METADATA]);
    }

    return metadata;
}

/**
 * Resolve the "meta" entry's key and value
 * 
 * @param {MetaTargetContext} targetContext
 * @param {Context} context
 * @param {Key | MetaCallback} key If callback is given, then it is invoked.
 *                                 It's resulting meta entry is returned.
 * @param {unknown} value Value to store as metadata. Ignored if callback is given
 *                        as key.
 *                        
 * @returns {MetaEntry}
 */
function resolveEntry(
    targetContext: MetaTargetContext,
    context: Context,
    key: Key | MetaCallback,
    value: unknown,
): MetaEntry
{
    if (typeof key === 'function') {
        return (key as MetaCallback)(targetContext.target, context, targetContext.owner);
    }

    return {
        key: (key as Key),
        value: value
    }
}

/**
 * Invokes the given initialisation callbacks
 * 
 * @param {MetaTargetContext} targetContext
 * @param {((this:any) => void)[]} callbacks
 */
function runIniCallbacks(targetContext: MetaTargetContext, callbacks: ((this: any /* eslint-disable-line @typescript-eslint/no-explicit-any */) => void)[]): void
{
    callbacks.forEach((callback: (this: any /* eslint-disable-line @typescript-eslint/no-explicit-any */) => void) => {
        callback.call(targetContext.thisArg);
    });
}

/**
 * Resolve the meta target context
 *
 * **Caution**: _`thisArg` should only be set from an "addInitializer" callback
 * function, via decorator context._
 * 
 * @param {object} target Target the is being decorated
 * @param {object} thisArg The bound "this" value, from "addInitializer" callback function.
 * @param {Context} context
 * 
 * @returns {MetaTargetContext}
 */
function resolveMetaTargetContext(
    target: object,
    thisArg: any, /* eslint-disable-line @typescript-eslint/no-explicit-any */
    context: Context
): MetaTargetContext
{
    return {
        owner: resolveTargetOwner(thisArg, context),
        thisArg: thisArg,
        target: target
    }
}

/**
 * Resolve the target's "owner"
 *
 * **Caution**: _`thisArg` should only be set from an "addInitializer" callback
 * function, via decorator context._
 * 
 * @param {object} thisArg The bound "this" value, from "addInitializer" callback function.
 * @param {Context} context
 * 
 * @returns {object} Target owner class
 */
function resolveTargetOwner(thisArg: object, context: Context): object
{
    if (context.kind === 'class') {
        return thisArg;
    }

    // @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/this#class_context
    return (context.static)
        ? thisArg
        // @ts-expect-error: When target is not static, then it's obtainable via prototype
        : Reflect.getPrototypeOf(thisArg).constructor;
}