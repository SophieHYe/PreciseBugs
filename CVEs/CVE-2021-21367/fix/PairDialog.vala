/*-
 * Copyright (c) 2018 elementary LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

public class PairDialog : Granite.MessageDialog {
    public enum AuthType {
        REQUEST_CONFIRMATION,
        REQUEST_AUTHORIZATION,
        DISPLAY_PASSKEY,
        DISPLAY_PIN_CODE
    }

    public ObjectPath object_path { get; construct; }
    public AuthType auth_type { get; construct; }
    public string passkey { get; construct; }
    public bool cancelled { get; set; }

    // Un-used default constructor
    private PairDialog () {
        Object (
            buttons: Gtk.ButtonsType.CANCEL
        );
    }

    public PairDialog.request_authorization (ObjectPath object_path, Gtk.Window? main_window) {
        Object (
            auth_type: AuthType.REQUEST_AUTHORIZATION,
            buttons: Gtk.ButtonsType.CANCEL,
            object_path: object_path,
            primary_text: _("Confirm Bluetooth Pairing"),
            transient_for: main_window
        );
    }

    public PairDialog.display_passkey (ObjectPath object_path, uint32 passkey, uint16 entered, Gtk.Window? main_window) {
        Object (
            auth_type: AuthType.DISPLAY_PASSKEY,
            buttons: Gtk.ButtonsType.CANCEL,
            object_path: object_path,
            passkey: "%u".printf (passkey),
            primary_text: _("Confirm Bluetooth Passkey"),
            transient_for: main_window
        );
    }

    public PairDialog.request_confirmation (ObjectPath object_path, uint32 passkey, Gtk.Window? main_window) {
        Object (
            auth_type: AuthType.REQUEST_CONFIRMATION,
            buttons: Gtk.ButtonsType.CANCEL,
            object_path: object_path,
            passkey: "%u".printf (passkey),
            primary_text: _("Confirm Bluetooth Passkey"),
            transient_for: main_window
        );
    }

    public PairDialog.display_pin_code (ObjectPath object_path, string pincode, Gtk.Window? main_window) {
        Object (
            auth_type: AuthType.DISPLAY_PIN_CODE,
            buttons: Gtk.ButtonsType.CANCEL,
            object_path: object_path,
            passkey: pincode,
            primary_text: _("Enter Bluetooth PIN"),
            transient_for: main_window
        );
    }

    construct {
        Bluetooth.Services.Device device;
        string device_name = _("Unknown Bluetooth Device");
        try {
            device = Bus.get_proxy_sync (BusType.SYSTEM, "org.bluez", object_path, DBusProxyFlags.GET_INVALIDATED_PROPERTIES);
            image_icon = new ThemedIcon (device.icon ?? "bluetooth");
            device_name = device.name ?? device.address;
        } catch (IOError e) {
            image_icon = new ThemedIcon ("bluetooth");
            critical (e.message);
        }

        switch (auth_type) {
            case AuthType.REQUEST_CONFIRMATION:
                badge_icon = new ThemedIcon ("dialog-password");
                secondary_text = _("Make sure the code displayed on “%s” matches the one below.").printf (device_name);

                var confirm_button = add_button (_("Pair"), Gtk.ResponseType.ACCEPT);
                confirm_button.get_style_context ().add_class (Gtk.STYLE_CLASS_SUGGESTED_ACTION);
                break;
            case AuthType.DISPLAY_PASSKEY:
                badge_icon = new ThemedIcon ("dialog-password");
                secondary_text = _("“%s” would like to pair with this device. Make sure the code displayed on “%s” matches the one below.").printf (device_name, device_name);

                var confirm_button = add_button (_("Pair"), Gtk.ResponseType.ACCEPT);
                confirm_button.get_style_context ().add_class (Gtk.STYLE_CLASS_SUGGESTED_ACTION);
                break;
            case AuthType.DISPLAY_PIN_CODE:
                badge_icon = new ThemedIcon ("dialog-password");
                secondary_text = _("Type the code displayed below on “%s”, followed by Enter.").printf (device_name);
                break;
            case AuthType.REQUEST_AUTHORIZATION:
                badge_icon = new ThemedIcon ("dialog-question");
                secondary_text = _("“%s” would like to pair with this device.").printf (device_name);

                var confirm_button = add_button (_("Pair"), Gtk.ResponseType.ACCEPT);
                confirm_button.get_style_context ().add_class (Gtk.STYLE_CLASS_SUGGESTED_ACTION);
                break;
        }

        if (passkey != null && passkey != "") {
            var passkey_label = new Gtk.Label (passkey);
            passkey_label.get_style_context ().add_class (Granite.STYLE_CLASS_H1_LABEL);

            custom_bin.add (passkey_label);
            custom_bin.show_all ();
        }

        modal = true;
    }
}
