import Big from "big.js";
import { CacheMap } from "./cachemap";
import { Crypto } from "./crypto";
import { IValidSlpTxidParams, IValidSlpTxnParams } from "./interfaces";
import { Slp, SlpTransactionDetails, SlpTransactionType, SlpVersionType } from "./slp";
import { Transaction } from "./transaction";

export interface Validation {
    validity: boolean|null;
    parents: Parent[];
    details: SlpTransactionDetails|null;
    invalidReason: string|null;
    waiting: boolean;
}

export type GetRawTransactionAsync = (txid: string) => Promise<string|Buffer>;

const sleep = (ms: number) => new Promise(resolve => setTimeout(resolve, ms));

interface Parent {
    txid: string;
    vout: number;
    versionType: number;
    valid: boolean|null;
    inputQty: Big|null;
}

export class ValidatorType1 {
    public cachedRawTransactions: CacheMap<string, Buffer>; // { [txid: string]: Buffer }
    public cachedValidations: CacheMap<string, Validation>; // { [txid: string]: Validation }
    public getRawTransaction: GetRawTransactionAsync;
    public logger: { log: (s: string) => any };

    constructor({ getRawTransaction, logger= console, maxTransactionCacheSize= 100000,
                maxValidationCacheSize= 100000 }: { getRawTransaction: GetRawTransactionAsync, logger?:
                    { log: (s: string) => any }, maxTransactionCacheSize?: number, maxValidationCacheSize?: number }) {
        if (!getRawTransaction) {
            throw Error("Must provide method getRawTransaction to class constructor.");
        }
        this.logger = logger;
        this.getRawTransaction = getRawTransaction;
        this.cachedValidations = new CacheMap<string, Validation>(maxValidationCacheSize);
        this.cachedRawTransactions = new CacheMap<string, Buffer>(maxTransactionCacheSize);
    }

    // WARNING: this method will be deprecated in next version
    public addValidationFromStore(txnHex: string, isValid: boolean) {
        const id = Crypto.HashTxid(Buffer.from(txnHex, "hex")).toString("hex");
        if (!this.cachedValidations.has(id)) {
            this.cachedValidations.set(id,
                { validity: isValid, parents: [], details: null, invalidReason: null, waiting: false });
        }
        if (!this.cachedRawTransactions.has(id)) {
            this.cachedRawTransactions.set(id, Buffer.from(txnHex, "hex"));
        }
    }

    public addValidTxnFromStore(txnHex: string) {
        const id = Crypto.HashTxid(Buffer.from(txnHex, "hex")).toString("hex");
        if (!this.cachedValidations.has(id)) {
            this.cachedValidations.set(id,
            {
                validity: true,
                parents: [],
                details: null,
                invalidReason: null,
                waiting: false
            });
        }
        if (!this.cachedRawTransactions.has(id)) {
            this.cachedRawTransactions.set(id, Buffer.from(txnHex, "hex"));
        }
    }

    public addValidTxidFromStore(txidHex: string) {
        if (!this.cachedValidations.has(txidHex)) {
            this.cachedValidations.set(txidHex,
            {
                validity: true,
                parents: [],
                details: null,
                invalidReason: null,
                waiting: false
            });
        }
    }

    public async waitForCurrentValidationProcessing(txid: string) {
        const cached: Validation = this.cachedValidations.get(txid)!;

        if (!cached) {
            throw Error("txid is missing from cachedValidations.");
        }

        while (true) {
            if (typeof cached.validity === "boolean") {
                cached.waiting = false;
                break;
            }
            await sleep(10);
        }
    }

    public async waitForTransactionDownloadToComplete(txid: string) {
        while (true) {
            // @ts-ignore
            if (this.cachedRawTransactions.get(txid)! && this.cachedRawTransactions.get(txid)! !== "waiting") {
                break;
            }
            await sleep(10);
        }
    }

    public async retrieveRawTransaction(txid: string) {
        if (this.cachedRawTransactions.has(txid)) {
            return this.cachedRawTransactions.get(txid)!;
        }
        // @ts-ignore
        this.cachedRawTransactions.set(txid, "waiting");
        const res = await this.getRawTransaction(txid);
        if (typeof res === "string") {
            this.cachedRawTransactions.set(txid, Buffer.from(res, "hex"));
        } else {
            this.cachedRawTransactions.set(txid, res);
        }
        if (this.cachedRawTransactions.has(txid)) {
            return this.cachedRawTransactions.get(txid)!;
        }
        throw Error("Transaction data not provided (null or undefined).");
    }

    /**
     * isValidSlpTxn
     *
     * Description:
     * This public method can be use with SEND type transactions to prevent accidental
     * of tokens having the same token ID.
     *
     * @param txn: hex string or buffer of the raw transaction to be validated
     * @param tokenIdFilter: (optional) token id of the token that should be considered valid
     * @param tokenTypeFilter: (optional) token type of the token that should be considered valid
     * @param burnQuantity: (optional) quantity of token to be burned (for same token id)
     *
     * Notes/Warnings:
     *   * This method does not yet check for burned inputs from another token IDs.
     *   * This method does not yet check for proper burn quanity of NFT1 parent
     *   * This method only works with SEND or type 0x01/0x81 GENESIS/MINT.
     *   * Burning operations must be done using a valid SLP SEND OP_RETURN message.
     */
    public async isValidSlpTxn({ txn, tokenIdFilter, tokenTypeFilter, burnQuantity= Big(0) }: IValidSlpTxnParams):
        Promise<boolean> {

        let txid;
        if (typeof txn === "string") {
            const txnBuf = Buffer.from(txn, "hex");
            txid = Crypto.HashTxid(txnBuf).toString("hex");
            this.cachedRawTransactions.set(txid, txnBuf);
        } else {
            txid = Crypto.HashTxid(txn).toString("hex");
            this.cachedRawTransactions.set(txid, txn);
        }

        const validity = await this.isValidSlpTxid({ txid, tokenIdFilter, tokenTypeFilter });
        if (!validity) {
            return validity;
        }
        const validation = this.cachedValidations.get(txid)!;
        let validInputs: Big;
        let outputs: Big;
        const txnType = validation.details!.transactionType;

        if (txnType === SlpTransactionType.SEND) {
            outputs = validation.details!.sendOutputs!.reduce((p, c) => p.plus(c), Big(0));
        } else if ([SlpTransactionType.GENESIS, SlpTransactionType.MINT].includes(txnType) &&
                   (SlpVersionType.TokenVersionType1 ||
                   SlpVersionType.TokenVersionType1_NFT_Parent)
        ) {
            return validity;
        } else {
            // This is here for NFT1 child type, since this method does not yet check accidental burning of NFT1 parent.
            throw Error("[slp-validate] isValidSlpTxn() for this type of transaction is not yet implemented (use 'isValidSlpTxid' instead).");
        }

        validInputs = validation.parents.map(p => p.inputQty ?
                                                    p.inputQty :
                                                    Big(0)).reduce((p, c) => p.plus(c), Big(0));

        if (burnQuantity.eq(0)) {
            if (!validInputs.eq(outputs)) {
                throw Error("[slp-validate] Outputs do not match valid inputs");
            }
        } else if (burnQuantity.gt(0)) {
            if (!validInputs.minus(burnQuantity).eq(outputs)) {
                throw Error("[slp-validate] Burn amount specified is not properly being burned in the provided transaction.");
            }
        }

        return validity;
    }

    /**
     * isValidSlpTxid
     *
     * Description:
     * This public method is used to determine validity of any type of token transaction
     *
     * @param txid: hex string or buffer of the raw transaction to be validated
     * @param tokenIdFilter: (optional) token id of the token that should be considered valid
     * @param tokenTypeFilter: (optional) token type of the token that should be considered valid
     *
     * Notes/Warnings:
     *   * This method does not prevent burning, for burn prevention checking in SEND
     *     use 'isValidSlpTxn()'.
     *
     */
    public async isValidSlpTxid(
        { txid, tokenIdFilter, tokenTypeFilter }: IValidSlpTxidParams): Promise<boolean> {
        this.logger.log("[slp-validate] Validating: " + txid);
        const valid = await this._isValidSlpTxid(txid, tokenIdFilter, tokenTypeFilter);
        this.logger.log("[slp-validate] Result: " + valid + " (" + txid + ")");
        if (!valid && this.cachedValidations.get(txid)!.invalidReason) {
            this.logger.log("[slp-validate] Invalid Reason: " + this.cachedValidations.get(txid)!.invalidReason);
        } else if (!valid) {
            this.logger.log("[slp-validate] Invalid Reason: unknown (result is user supplied)");
        }
        return valid;
    }

    public async validateSlpTransactions(txids: string[]): Promise<string[]> {
        const res = [];
        for (const txid of txids) {
            res.push((await this.isValidSlpTxid({ txid })) ? txid : "");
        }
        return res.filter((id: string) => id.length > 0);
    }

    /**
     * _isValidSlpTxid
     *
     * Description:
     * This internal method uses recursion to do a Depth-First-Search with the node result being
     * computed in Postorder Traversal (left/right/root) order.  A validation cache
     * is used to keep track of the results for nodes that have already been evaluated.
     *
     * Each call to this method evaluates node validity with respect to
     * its parent node(s), so it walks backwards until the
     * validation cache provides a result or the GENESIS node is evaluated.
     *
     * Root nodes await the validation result of their upstream parent.
     * In the case of NFT1 the search continues to the group/parent NFT DAG after the Genesis
     * of the NFT child is discovered.
     *
     * @param txid: hex string or buffer of the raw transaction to be validated
     * @param tokenIdFilter: (optional) token id of the token that should be considered valid
     * @param tokenTypeFilter: (optional) token type of the token that should be considered valid
     *
     */
    private async _isValidSlpTxid(txid: string, tokenIdFilter?: string, tokenTypeFilter?: number): Promise<boolean> {
        // Check to see if this txn has been processed by looking at shared cache, if doesn't exist then download txn.
        if (!this.cachedValidations.has(txid)) {
            this.cachedValidations.set(txid, {
                details: null,
                invalidReason: null,
                parents: [],
                validity: null,
                waiting: false,
            });
            await this.retrieveRawTransaction(txid);
        } else if (typeof this.cachedValidations.get(txid)!.validity === "boolean" &&
                  !tokenIdFilter && !tokenTypeFilter) {
            return this.cachedValidations.get(txid)!.validity!;
        }

        //
        // Handle the case where neither branch of the previous if/else statement was
        // executed and the raw transaction has never been downloaded.
        //
        // Also handle case where a 2nd request of same txid comes in
        // during the download of a previous request.
        //
        // @ts-ignore
        if (!this.cachedRawTransactions.get(txid)! || this.cachedRawTransactions.get(txid)! === "waiting") {
            // @ts-ignore
            if (this.cachedRawTransactions.get(txid)! !== "waiting") {
                this.retrieveRawTransaction(txid);
            }

            // Wait for previously a initiated download to completed
            await this.waitForTransactionDownloadToComplete(txid);
        }

        const validation = this.cachedValidations.get(txid)!;
        const transaction = this.cachedRawTransactions.get(txid)!;

        // Handle case where txid is already in the process of being validated from a previous call
        if (validation.waiting) {
            await this.waitForCurrentValidationProcessing(txid);
            if (typeof validation.validity === "boolean" && !tokenIdFilter && !tokenTypeFilter) {
                return validation.validity!;
            }
        }

        validation.waiting = true;

        // Check SLP message validity
        const txn: Transaction = Transaction.parseFromBuffer(transaction);
        let slpmsg: SlpTransactionDetails;
        try {
            slpmsg = validation.details = Slp.parseSlpOutputScript(txn.outputs[0].scriptPubKey);
            if (slpmsg.transactionType === SlpTransactionType.GENESIS) {
                slpmsg.tokenIdHex = txid;
            }
        } catch (e) {
            validation.validity = false;
            validation.waiting = false;
            validation.invalidReason = "SLP OP_RETURN parsing error (" + e.message + ").";
            return validation.validity!;
        }

        // Check for tokenId filter
        if (tokenIdFilter && slpmsg.tokenIdHex !== tokenIdFilter) {
            validation.waiting = false;
            validation.invalidReason = "Validator was run with filter only considering tokenId " + tokenIdFilter + " as valid.";
            return false; // Don't save boolean result to cache incase cache is ever used without tokenIdFilter.
        } else {
            if (validation.validity !== false) {
                validation.invalidReason = null;
            }
        }

        // Check specified token type is being respected
        if (tokenTypeFilter && slpmsg.versionType !== tokenTypeFilter) {
            validation.validity = null;
            validation.waiting = false;
            validation.invalidReason = "Validator was run with filter only considering token type: " + tokenTypeFilter + " as valid.";
            return false; // Don't save boolean result to cache incase cache is ever used with different token type.
        } else {
            if (validation.validity !== false) {
                validation.invalidReason = null;
            }
        }

        // Check DAG validity
        if (slpmsg.transactionType === SlpTransactionType.GENESIS) {
            // Check for NFT1 child (type 0x41)
            if (slpmsg.versionType === 0x41) {
                // An NFT1 parent should be provided at input index 0,
                // so we check this first before checking the whole parent DAG
                const inputTxid = txn.inputs[0].previousTxHash;
                const inputTxHex = await this.retrieveRawTransaction(inputTxid);
                const inputTx: Transaction = Transaction.parseFromBuffer(inputTxHex);
                let inputSlpMsg;
                try {
                    inputSlpMsg = Slp.parseSlpOutputScript(inputTx.outputs[0].scriptPubKey);
                } catch (_) {}
                if (!inputSlpMsg || inputSlpMsg.versionType !== 0x81) {
                    validation.validity = false;
                    validation.waiting = false;
                    validation.invalidReason = "NFT1 child GENESIS does not have a valid NFT1 parent input.";
                    return validation.validity!;
                }
                // Check that the there is a burned output >0 in the parent txn SLP message
                if (inputSlpMsg.transactionType === SlpTransactionType.SEND &&
                    !inputSlpMsg.sendOutputs![1].gt(0)) {
                    validation.validity = false;
                    validation.waiting = false;
                    validation.invalidReason = "NFT1 child's parent has SLP output that is not greater than zero.";
                    return validation.validity!;
                } else if ((inputSlpMsg.transactionType === SlpTransactionType.GENESIS ||
                            inputSlpMsg.transactionType === SlpTransactionType.MINT) &&
                            !inputSlpMsg.genesisOrMintQuantity!.gt(0)) {
                    validation.validity = false;
                    validation.waiting = false;
                    validation.invalidReason = "NFT1 child's parent has SLP output that is not greater than zero.";
                    return validation.validity!;
                }
                // Continue to check the NFT1 parent DAG
                const nft_parent_dag_validity = await this.isValidSlpTxid({
                    txid: inputTxid,
                    tokenIdFilter: undefined,
                    tokenTypeFilter: 0x81
                });
                validation.validity = nft_parent_dag_validity;
                validation.waiting = false;
                if (!nft_parent_dag_validity) {
                    validation.invalidReason = "NFT1 child GENESIS does not have valid parent DAG.";
                }
                return validation.validity!;
            } else {
                validation.validity = true;
                validation.waiting = false;
                return validation.validity!;
            }
        } else if (slpmsg.transactionType === SlpTransactionType.MINT) {
            for (let i = 0; i < txn.inputs.length; i++) {
                const inputTxid = txn.inputs[i].previousTxHash;
                const inputTxHex = await this.retrieveRawTransaction(inputTxid);
                const inputTx: Transaction = Transaction.parseFromBuffer(inputTxHex);
                try {
                    const inputSlpMsg = Slp.parseSlpOutputScript(inputTx.outputs[0].scriptPubKey);
                    if (inputSlpMsg.transactionType === SlpTransactionType.GENESIS) {
                        inputSlpMsg.tokenIdHex = inputTxid;
                    }
                    if (inputSlpMsg.tokenIdHex === slpmsg.tokenIdHex) {
                        if (inputSlpMsg.transactionType === SlpTransactionType.GENESIS ||
                            inputSlpMsg.transactionType === SlpTransactionType.MINT) {
                            if (txn.inputs[i].previousTxOutIndex === inputSlpMsg.batonVout) {
                                validation.parents.push({
                                    txid: txn.inputs[i].previousTxHash,
                                    vout: txn.inputs[i].previousTxOutIndex,
                                    versionType: inputSlpMsg.versionType,
                                    valid: null,
                                    inputQty: null,
                                });
                            }
                        }
                    }
                } catch (_) { }
            }
            if (validation.parents.length < 1) {
                validation.validity = false;
                validation.waiting = false;
                validation.invalidReason = "MINT transaction must have at least 1 candidate baton parent input.";
                return validation.validity!;
            }
        } else if (slpmsg.transactionType === SlpTransactionType.SEND) {
            const tokenOutQty = slpmsg.sendOutputs!.reduce((t, v) => t.plus(v), new Big(0));
            let tokenInQty = new Big(0);
            for (let i = 0; i < txn.inputs.length; i++) {
                const inputTxid = txn.inputs[i].previousTxHash;
                const inputTxHex = await this.retrieveRawTransaction(inputTxid);
                const inputTx: Transaction = Transaction.parseFromBuffer(inputTxHex);
                try {
                    const inputSlpMsg = Slp.parseSlpOutputScript(inputTx.outputs[0].scriptPubKey);
                    if (inputSlpMsg.transactionType === SlpTransactionType.GENESIS) {
                        inputSlpMsg.tokenIdHex = inputTxid;
                    }
                    if (inputSlpMsg.tokenIdHex === slpmsg.tokenIdHex) {
                        if (inputSlpMsg.transactionType === SlpTransactionType.SEND) {
                            if (txn.inputs[i].previousTxOutIndex <= inputSlpMsg.sendOutputs!.length - 1) {
                                tokenInQty = tokenInQty.plus(
                                    inputSlpMsg.sendOutputs![txn.inputs[i].previousTxOutIndex]);
                                validation.parents.push({
                                    txid: txn.inputs[i].previousTxHash,
                                    vout: txn.inputs[i].previousTxOutIndex,
                                    versionType: inputSlpMsg.versionType,
                                    valid: null,
                                    inputQty: inputSlpMsg.sendOutputs![txn.inputs[i].previousTxOutIndex],
                                });
                            }
                        } else if (inputSlpMsg.transactionType === SlpTransactionType.GENESIS ||
                                   inputSlpMsg.transactionType === SlpTransactionType.MINT) {
                            if (txn.inputs[i].previousTxOutIndex === 1) {
                                tokenInQty = tokenInQty.plus(inputSlpMsg.genesisOrMintQuantity!);
                                validation.parents.push({
                                    txid: txn.inputs[i].previousTxHash,
                                    vout: txn.inputs[i].previousTxOutIndex,
                                    versionType: inputSlpMsg.versionType,
                                    valid: null,
                                    inputQty: inputSlpMsg.genesisOrMintQuantity,
                                });
                            }
                        }
                    }
                } catch (_) { }
            }

            // Check token inputs are greater than token outputs (includes valid and invalid inputs)
            if (tokenOutQty.gt(tokenInQty)) {
                validation.validity = false;
                validation.waiting = false;
                validation.invalidReason = "Token outputs are greater than possible token inputs.";
                return validation.validity!;
            }
        }

        // Set validity validation-cache for parents, and handle MINT condition with no valid input
        // we don't need to check proper token id since we only added parents with same ID in above steps.
        const parentTxids = [...new Set(validation.parents.map(p => p.txid))];
        for (const id of parentTxids) {
            const valid = await this.isValidSlpTxid({ txid: id });
            validation.parents.filter(p => p.txid === id).map(p => p.valid = valid);
        }

        // Check MINT for exactly 1 valid MINT baton
        if (validation.details!.transactionType === SlpTransactionType.MINT) {
            if (validation.parents.filter(p => p.valid && p.inputQty === null).length !== 1) {
                validation.validity = false;
                validation.waiting = false;
                validation.invalidReason = "MINT transaction with invalid baton parent.";
                return validation.validity!;
            }
        }

        // Check valid inputs are greater than token outputs
        if (validation.details!.transactionType === SlpTransactionType.SEND) {
            const validInputQty = validation.parents.reduce((t, v) => v.valid ? t.plus(v.inputQty!) : t, new Big(0));
            const tokenOutQty = slpmsg.sendOutputs!.reduce((t, v) => t.plus(v), new Big(0));
            if (tokenOutQty.gt(validInputQty)) {
                validation.validity = false;
                validation.waiting = false;
                validation.invalidReason = "Token outputs are greater than valid token inputs.";
                return validation.validity!;
            }
        }

        // Check versionType is not different from valid parents
        if (validation.parents.filter(p => p.valid).length > 0) {
            const validVersionType = validation.parents.find(p => p.valid!)!.versionType;
            if (validation.details!.versionType !== validVersionType) {
                validation.validity = false;
                validation.waiting = false;
                validation.invalidReason = "SLP version/type mismatch from valid parent.";
                return validation.validity!;
            }
        }
        validation.validity = true;
        validation.waiting = false;
        return validation.validity!;
    }
}
