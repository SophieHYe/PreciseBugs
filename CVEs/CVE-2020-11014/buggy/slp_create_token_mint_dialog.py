import copy
import datetime
from functools import partial
import json
import threading
import sys
import traceback
import math

from PyQt5.QtCore import *
from PyQt5.QtGui import *
from PyQt5.QtWidgets import *

from electroncash.address import Address, PublicKey
from electroncash.bitcoin import base_encode, TYPE_ADDRESS
from electroncash.i18n import _
from electroncash.plugins import run_hook

from .util import *

from electroncash.util import bfh, format_satoshis_nofloat, format_satoshis_plain_nofloat, NotEnoughFunds, ExcessiveFee, PrintError, finalization_print_error
from electroncash.transaction import Transaction
from electroncash.slp import SlpMessage, SlpNoMintingBatonFound, SlpUnsupportedSlpTokenType, SlpInvalidOutputMessage, buildMintOpReturnOutput_V1

from .amountedit import SLPAmountEdit
from .transaction_dialog import show_transaction

from electroncash import networks

dialogs = []  # Otherwise python randomly garbage collects the dialogs...

class SlpCreateTokenMintDialog(QDialog, MessageBoxMixin, PrintError):

    def __init__(self, main_window, token_id_hex):
        # We want to be a top-level window
        QDialog.__init__(self, parent=None)
        from .main_window import ElectrumWindow

        assert isinstance(main_window, ElectrumWindow)
        main_window._slp_dialogs.add(self)
        finalization_print_error(self)  # Track object lifecycle

        self.main_window = main_window
        self.wallet = main_window.wallet
        self.network = main_window.network
        self.app = main_window.app

        if self.main_window.gui_object.warn_if_no_network(self.main_window):
            return

        self.setWindowTitle(_("Mint Additional Tokens"))

        vbox = QVBoxLayout()
        self.setLayout(vbox)

        grid = QGridLayout()
        grid.setColumnStretch(1, 1)
        vbox.addLayout(grid)
        row = 0

        msg = _('Unique identifier for the token.')
        grid.addWidget(HelpLabel(_('Token ID:'), msg), row, 0)

        self.token_id_e = QLineEdit()
        self.token_id_e.setFixedWidth(490)
        self.token_id_e.setText(token_id_hex)
        self.token_id_e.setDisabled(True)
        grid.addWidget(self.token_id_e, row, 1)
        row += 1

        msg = _('The number of decimal places used in the token quantity.')
        grid.addWidget(HelpLabel(_('Decimals:'), msg), row, 0)
        self.token_dec = QDoubleSpinBox()
        decimals = self.main_window.wallet.token_types.get(token_id_hex)['decimals']
        self.token_dec.setRange(0, 9)
        self.token_dec.setValue(decimals)
        self.token_dec.setDecimals(0)
        self.token_dec.setFixedWidth(50)
        self.token_dec.setDisabled(True)
        grid.addWidget(self.token_dec, row, 1)
        row += 1

        msg = _('The number of tokens created during token minting transaction, send to the receiver address provided below.')
        grid.addWidget(HelpLabel(_('Additional Token Quantity:'), msg), row, 0)
        name = self.main_window.wallet.token_types.get(token_id_hex)['name']
        self.token_qty_e = SLPAmountEdit(name, int(decimals))
        self.token_qty_e.setFixedWidth(200)
        self.token_qty_e.textChanged.connect(self.check_token_qty)
        grid.addWidget(self.token_qty_e, row, 1)
        row += 1

        msg = _('The simpleledger formatted bitcoin address for the genesis receiver of all genesis tokens.')
        grid.addWidget(HelpLabel(_('Token Receiver Address:'), msg), row, 0)
        self.token_pay_to_e = ButtonsLineEdit()
        self.token_pay_to_e.setFixedWidth(490)
        grid.addWidget(self.token_pay_to_e, row, 1)
        row += 1

        msg = _('The simpleledger formatted bitcoin address for the genesis baton receiver.')
        self.token_baton_label = HelpLabel(_('Mint Baton Address:'), msg)
        grid.addWidget(self.token_baton_label, row, 0)
        self.token_baton_to_e = ButtonsLineEdit()
        self.token_baton_to_e.setFixedWidth(490)
        grid.addWidget(self.token_baton_to_e, row, 1)
        row += 1

        try:
            slpAddr = self.wallet.get_unused_address().to_slpaddr()
            self.token_pay_to_e.setText(Address.prefix_from_address_string(slpAddr) + ":" + slpAddr)
            self.token_baton_to_e.setText(Address.prefix_from_address_string(slpAddr) + ":" + slpAddr)
        except Exception as e:
            pass

        self.token_fixed_supply_cb = cb = QCheckBox(_('Permanently end issuance'))
        self.token_fixed_supply_cb.setChecked(False)
        grid.addWidget(self.token_fixed_supply_cb, row, 0)
        cb.clicked.connect(self.show_mint_baton_address)
        row += 1

        hbox = QHBoxLayout()
        vbox.addLayout(hbox)

        self.cancel_button = b = QPushButton(_("Cancel"))
        self.cancel_button.setAutoDefault(False)
        self.cancel_button.setDefault(False)
        b.clicked.connect(self.close)
        b.setDefault(True)
        hbox.addWidget(self.cancel_button)

        hbox.addStretch(1)

        self.preview_button = EnterButton(_("Preview"), self.do_preview)
        self.mint_button = b = QPushButton(_("Create Additional Tokens"))
        b.clicked.connect(self.mint_token)
        self.mint_button.setAutoDefault(True)
        self.mint_button.setDefault(True)
        hbox.addWidget(self.preview_button)
        hbox.addWidget(self.mint_button)

        dialogs.append(self)
        self.show()
        self.token_qty_e.setFocus()

    def do_preview(self):
        self.mint_token(preview = True)

    def show_mint_baton_address(self):
        self.token_baton_to_e.setHidden(self.token_fixed_supply_cb.isChecked())
        self.token_baton_label.setHidden(self.token_fixed_supply_cb.isChecked())

    def parse_address(self, address):
        if networks.net.SLPADDR_PREFIX not in address:
            address = networks.net.SLPADDR_PREFIX + ":" + address
        return Address.from_string(address)

    def mint_token(self, preview=False):
        decimals = int(self.token_dec.value())
        mint_baton_vout = 2 if self.token_baton_to_e.text() != '' and not self.token_fixed_supply_cb.isChecked() else None
        init_mint_qty = self.token_qty_e.get_amount()
        if init_mint_qty is None:
            self.show_message(_("Invalid token quantity entered."))
            return
        if init_mint_qty > (2 ** 64) - 1:
            maxqty = format_satoshis_plain_nofloat((2 ** 64) - 1, decimals)
            self.show_message(_("Token output quantity is too large. Maximum %s.")%(maxqty,))
            return

        outputs = []
        try:
            token_id_hex = self.token_id_e.text()
            token_type = self.wallet.token_types[token_id_hex]['class']
            slp_op_return_msg = buildMintOpReturnOutput_V1(token_id_hex, mint_baton_vout, init_mint_qty, token_type)
            outputs.append(slp_op_return_msg)
        except OPReturnTooLarge:
            self.show_message(_("Optional string text causiing OP_RETURN greater than 223 bytes."))
            return
        except Exception as e:
            traceback.print_exc(file=sys.stdout)
            self.show_message(str(e))
            return

        try:
            addr = self.parse_address(self.token_pay_to_e.text())
            outputs.append((TYPE_ADDRESS, addr, 546))
        except:
            self.show_message(_("Enter a Mint Receiver Address in SLP address format."))
            return

        if not self.token_fixed_supply_cb.isChecked():
            try:
                addr = self.parse_address(self.token_baton_to_e.text())
                outputs.append((TYPE_ADDRESS, addr, 546))
            except:
                self.show_message(_("Enter a Baton Address in SLP address format."))
                return

        # IMPORTANT: set wallet.sedn_slpTokenId to None to guard tokens during this transaction
        self.main_window.token_type_combo.setCurrentIndex(0)
        assert self.main_window.slp_token_id == None

        coins = self.main_window.get_coins()
        fee = None

        try:
            baton_input = self.main_window.wallet.get_slp_token_baton(self.token_id_e.text())
        except SlpNoMintingBatonFound as e:
            self.show_message(_("No baton exists for this token."))
            return

        desired_fee_rate = 1.0  # sats/B, just init this value for paranoia
        try:
            tx = self.main_window.wallet.make_unsigned_transaction(coins, outputs, self.main_window.config, fee, None)
            desired_fee_rate = tx.get_fee() / tx.estimated_size()  # remember the fee coin chooser & wallet gave us as a fee rate so we may use it below after adding baton to adjust fee downward to this rate.
        except NotEnoughFunds:
            self.show_message(_("Insufficient funds"))
            return
        except ExcessiveFee:
            self.show_message(_("Your fee is too high.  Max is 50 sat/byte."))
            return
        except BaseException as e:
            traceback.print_exc(file=sys.stdout)
            self.show_message(str(e))
            return

        # Find & Add baton to tx inputs
        try:
            baton_utxo = self.main_window.wallet.get_slp_token_baton(self.token_id_e.text())
        except SlpNoMintingBatonFound:
            self.show_message(_("There is no minting baton found for this token."))
            return

        tx.add_inputs([baton_utxo])
        for txin in tx._inputs:
            self.main_window.wallet.add_input_info(txin)

        def tx_adjust_change_amount_based_on_baton_amount(tx, desired_fee_rate):
            ''' adjust change amount (based on amount added from baton) '''
            if len(tx._outputs) not in (3,4):
                # no change, or a tx shape we don't know about
                self.print_error(f"Unkown tx shape, not adjusting fee!")
                return
            chg = tx._outputs[-1]  # change is always the last output due to BIP_LI01 sorting
            assert len(chg) == 3, "Expected tx output to be of length 3"
            if not self.main_window.wallet.is_mine(chg[1]):
                self.print_error(f"Unkown change address {chg[1]}, not adjusting fee!")
                return
            chg_amt = chg[2]
            if chg_amt <= 546:
                # if change is 546, then the BIP_LI01 sorting doesn't guarantee
                # change output is at the end.. so we don't know which was
                # changed based on the heuristics this code relies on.. so..
                # Abort! Abort!
                self.print_error("Could not determine change output, not adjusting fee!")
                return
            curr_fee, curr_size = tx.get_fee(), tx.estimated_size()
            fee_rate = curr_fee / curr_size
            diff = math.ceil((fee_rate - desired_fee_rate) * curr_size)
            if diff > 0:
                tx._outputs[-1] = (chg[0], chg[1], chg[2] + diff)  # adjust the output
                self.print_error(f"Added {diff} sats to change to maintain fee rate of {desired_fee_rate:0.2f}, new fee: {tx.get_fee()}")

        tx_adjust_change_amount_based_on_baton_amount(tx, desired_fee_rate)

        if preview:
            show_transaction(tx, self.main_window, None, False, self)
            return

        msg = []

        if self.main_window.wallet.has_password():
            msg.append("")
            msg.append(_("Enter your password to proceed"))
            password = self.main_window.password_dialog('\n'.join(msg))
            if not password:
                return
        else:
            password = None

        tx_desc = None

        def sign_done(success):
            if success:
                if not tx.is_complete():
                    show_transaction(tx, self.main_window, None, False, self)
                    self.main_window.do_clear()
                else:
                    self.main_window.broadcast_transaction(tx, tx_desc)

        self.main_window.sign_tx_with_password(tx, sign_done, password)

        self.mint_button.setDisabled(True)
        self.close()

    def closeEvent(self, event):
        super().closeEvent(event)
        event.accept()
        def remove_self():
            try: dialogs.remove(self)
            except ValueError: pass  # wasn't in list.
        QTimer.singleShot(0, remove_self)  # need to do this some time later. Doing it from within this function causes crashes. See #35

    def update(self):
        return

    def check_token_qty(self):
        try:
            if self.token_qty_e.get_amount() > (10 ** 19):
                self.show_warning(_('If you issue this much, users will may find it awkward to transfer large amounts as each transaction output may only take up to ~2 x 10^(19-decimals) tokens, thus requiring multiple outputs for very large amounts.'))
        except:
            pass
