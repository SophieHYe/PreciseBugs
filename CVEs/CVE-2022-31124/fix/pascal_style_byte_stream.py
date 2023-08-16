"""Classes for manipulating bytestreams containing values that may be fixed-
or variable-size.
"""

import abc
import enum
import io
import struct
import types
import typing
import warnings

from openssh_key import utils


class PascalStyleFormatInstruction(enum.Enum):
    """Format instructions for variable-length values that may appear in a
    :py:class:`PascalStyleByteStream` representing an OpenSSH key, per
    `RFC 4251 <https://tools.ietf.org/html/rfc4251#section-5>`_.
    """
    BYTES = bytes
    """
    A Python :py:class:`bytes`.
    """

    STRING = str
    """
    A Python :py:class:`str`.
    """

    MPINT = int
    """
    A big-endian, signed :py:class:`int` value.
    """


class PascalStyleFormatInstructionStringLengthSize(typing.NamedTuple):
    """A format instruction for a variable-length value, and the size,
    in bytes, of the unsigned ``int`` prefix describing the value's length
    in bytes.

    Example:
        Reading ``b'\\x00\\x00\\x03abc'`` with a
        :any:`format_instruction` of
        :any:`PascalStyleFormatInstruction.STRING` and a
        :any:`string_length_size` of ``3`` yields a ``str``, viz.
        ``'abc'``.
    """
    format_instruction: PascalStyleFormatInstruction
    """The format instruction.
    """
    string_length_size: int
    """The size of the length prefix.
    """


FormatInstructionsDict = typing.Mapping[
    str,
    typing.Union[
        str,
        PascalStyleFormatInstruction,
        PascalStyleFormatInstructionStringLengthSize
    ]
]


ValuesDict = typing.Mapping[
    str,
    typing.Any
]


class PascalStyleByteStream(io.BytesIO):
    """Methods on :py:class:`io.BytesIO` that allow reading and writing values
    either as ``struct`` values, or as Pascal-style values: variable-length
    ``bytes``, ``str``, or variable-precision ``int`` values prefixed by the
    length of such variable-length value.
    """

    @staticmethod
    def get_openssh_default_string_length_size() -> int:
        """
        The value 4, the size in bytes used by OpenSSH for the ``int`` preceding
        a variable-length value that indicates the length of the latter.
        """
        return 4

    OPENSSH_DEFAULT_STRING_LENGTH_SIZE = utils.readonly_static_property(
        get_openssh_default_string_length_size
    )
    """
    The value 4, the size in bytes used by OpenSSH for the ``int`` preceding
    a variable-length value that indicates the length of the latter.
    """

    def read_from_format_instruction(
        self,
        format_instruction: typing.Union[str, PascalStyleFormatInstruction],
        string_length_size: typing.Optional[int] = None
    ) -> typing.Any:
        """Reads a value from the underlying bytestream according to a format
        instruction.

        Args:
            format_instruction
                A format instruction; either a ``struct``
                `format string <https://docs.python.org/3/library/struct.html#format-strings>`_,
                or a :any:`PascalStyleFormatInstruction`.
            string_length_size
                If ``format_instruction`` is a
                :any:`PascalStyleFormatInstruction`, the size in bytes of the
                ``int`` preceding the variable-length value that indicates the
                length of the latter. Ignored otherwise. The default is 4,
                which OpenSSH uses for encoding keys.

        Returns:
            The read value. If ``format_instruction`` is a ``struct``
            format string, the value unpacked using
            :py:func:`struct.unpack`; if ``format_instruction``
            is a :any:`PascalStyleFormatInstruction`, the value converted
            to the corresponding class.

        Raises:
            EOFError: The underlying bytestream does not contain enough bytes
                to read a complete value according to ``format_instruction``.
            ValueError: ``string_length_size`` is nonpositive.
        """
        if string_length_size is None:
            string_length_size = PascalStyleByteStream.OPENSSH_DEFAULT_STRING_LENGTH_SIZE
        if isinstance(format_instruction, str):
            calcsize = struct.calcsize(format_instruction)
            read_bytes = self.read_fixed_bytes(calcsize)
            read_unpack = struct.unpack(format_instruction, read_bytes)
            if len(read_unpack) == 1:
                return read_unpack[0]
            return read_unpack
        elif isinstance(format_instruction, PascalStyleFormatInstruction):
            read_bytes = self.read_pascal_bytes(string_length_size)
            if format_instruction == PascalStyleFormatInstruction.BYTES:
                return read_bytes
            elif format_instruction == PascalStyleFormatInstruction.STRING:
                return read_bytes.decode()
            elif format_instruction == PascalStyleFormatInstruction.MPINT:
                return int.from_bytes(
                    read_bytes,
                    byteorder='big',
                    signed=True
                )
        raise NotImplementedError()

    def read_from_format_instructions_dict(
        self,
        format_instructions_dict: FormatInstructionsDict
    ) -> ValuesDict:
        """Reads values from the underlying bytestream according to a
        :py:class:`typing.Mapping` of format instructions.

        Args:
            format_instructions_dict
                A :py:class:`typing.Mapping` of value names to format
                instructions.

        Returns:
            A :py:class:`typing.Mapping` of value names to read values, as
            per :any:`read_from_format_instruction`.

        Raises:
            EOFError: The underlying bytestream does not contain enough bytes
                to read a complete value for one of the format instructions in
                ``format_instructions_dict``.
        """
        return {
            k: (
                self.read_from_format_instruction(
                    format_instruction.format_instruction,
                    format_instruction.string_length_size
                ) if isinstance(
                    format_instruction,
                    PascalStyleFormatInstructionStringLengthSize
                )
                else self.read_from_format_instruction(format_instruction)
            )
            for k, format_instruction in format_instructions_dict.items()
        }

    def read_repeatedly_from_format_instructions_dict(
        self,
        format_instructions_dict: FormatInstructionsDict
    ) -> typing.List[typing.Any]:
        """Reads values repeatedly as per
        :any:`read_from_format_instructions_dict` until the stream is
        exhausted.

        Args:
            format_instructions_dict
                A :py:class:`typing.Mapping` of value names to format
                instructions.

        Returns:
            A :py:class:`typing.List` of :py:class:`typing.Mapping` of value
            names to read values.

        Raises:
            EOFError: The underlying bytestream does not contain enough bytes
                to read a complete value for one of the format instructions in
                ``format_instructions_dict``.
        """
        if len(format_instructions_dict) == 0:
            raise ValueError('format_instructions_dict cannot be empty')
        l = []
        while True:
            try:
                l.append(
                    self.read_from_format_instructions_dict(
                        format_instructions_dict
                    )
                )
            except EOFError as e:
                if len(e.args[0]) == 0:
                    return l
                raise

    def read_fixed_bytes(self, num_bytes: int) -> bytes:
        """Reads a fixed number of bytes from the underlying bytestream.

        Args:
            num_bytes
                The number of bytes to read.

        Returns:
            The read bytes.

        Raises:
            EOFError: Fewer than ``num_bytes`` bytes remained in the
                underlying bytestream.
        """
        read_bytes = self.read(num_bytes)
        if len(read_bytes) < num_bytes:
            raise EOFError("Fewer than 'num_bytes' bytes remaining in the "
                    "underlying bytestream")
        return read_bytes

    def read_pascal_bytes(self, string_length_size: int) -> bytes:
        """Reads a Pascal-style byte string from the underlying bytestream,
        given the size of the length prefix.

        Args:
            string_length_size
                The size of the big-endian unsigned ``int`` prefix that
                indicates the length of the byte string to read.

        Returns:
            The read byte string.

        Raises:
            EOFError: Fewer than ``string_length_size`` bytes remained in the
                underlying bytestream, or the length prefix exceeds the number
                of bytes remaining in the underlying bytestream.
            ValueError: ``string_length_size`` is nonpositive.
        """
        if string_length_size <= 0:
            raise ValueError('string_length_size must be positive')
        length = int.from_bytes(
            self.read_fixed_bytes(string_length_size),
            byteorder='big'
        )
        return self.read_fixed_bytes(length)

    def write_from_format_instruction(
        self,
        format_instruction: typing.Union[str, PascalStyleFormatInstruction],
        value: typing.Any,
        string_length_size: typing.Optional[int] = None
    ) -> None:
        """Writes a value to the underlying bytestream according to a format
        instruction.

        Args:
            format_instruction
                A format instruction; either a ``struct``
                `format string <https://docs.python.org/3/library/struct.html#format-strings>`_,
                or a :any:`PascalStyleFormatInstruction`.
            value
                The value to write.
            string_length_size
                If ``format_instruction`` is a
                :any:`PascalStyleFormatInstruction`, the size in bytes of the
                ``int`` preceding the variable-length value that indicates the
                length of the latter. Ignored otherwise. The default is 4,
                which OpenSSH uses for encoding keys.
        """
        if string_length_size is None:
            string_length_size = PascalStyleByteStream.OPENSSH_DEFAULT_STRING_LENGTH_SIZE
        write_bytes = None
        if isinstance(format_instruction, str):
            write_bytes = struct.pack(format_instruction, value)
        elif isinstance(format_instruction, PascalStyleFormatInstruction):
            if format_instruction == PascalStyleFormatInstruction.BYTES:
                if not isinstance(value, bytes):
                    raise ValueError(
                        'value must be a bytes instance for bytes '
                        'format instruction'
                    )
                write_bytes = value
            elif format_instruction == PascalStyleFormatInstruction.STRING:
                if not isinstance(value, str):
                    raise ValueError(
                        'value must be a str instance for string '
                        'format instruction'
                    )
                write_bytes = value.encode()
            elif format_instruction == PascalStyleFormatInstruction.MPINT:
                if not isinstance(value, int):
                    raise ValueError(
                        'value must be an int instance for mpint '
                        'format instruction'
                    )
                write_bytes = value.to_bytes(
                    length=(value.bit_length() + (8 if value > 0 else 7)) // 8,
                    byteorder='big',
                    signed=True
                )
            else:
                raise NotImplementedError()
            write_bytes_len_bytes = len(write_bytes).to_bytes(
                length=string_length_size,
                byteorder='big',
                signed=False
            )
            write_bytes = write_bytes_len_bytes + write_bytes
        else:
            raise NotImplementedError()
        self.write(write_bytes)

    def write_from_format_instructions_dict(
        self,
        format_instructions_dict: FormatInstructionsDict,
        values_dict: ValuesDict
    ) -> None:
        """Writes values to the underlying bytestream according to a
        :py:class:`typing.Mapping` of format instructions.

        Args:
            format_instructions_dict
                A :py:class:`typing.Mapping` of value names to format
                instructions.
            values_dict
                A :py:class:`typing.Mapping` of value names to values to
                be written.

        Raises:
            KeyError: ``values_dict`` does not contain a key that is
                contained in ``format_instructions_dict``.
        """
        for k, format_instruction in format_instructions_dict.items():
            if isinstance(
                format_instruction,
                PascalStyleFormatInstructionStringLengthSize
            ):
                self.write_from_format_instruction(
                    format_instruction.format_instruction,
                    values_dict[k],
                    format_instruction.string_length_size
                )
            else:
                self.write_from_format_instruction(
                    format_instruction,
                    values_dict[k]
                )

    def write_repeatedly_from_format_instructions_dict(
        self,
        format_instructions_dict: FormatInstructionsDict,
        values_dicts: typing.Sequence[ValuesDict]
    ) -> None:
        """Writes a list of values to the underlying bytestream as per
        :any:`write_from_format_instructions_dict`.

        Args:
            format_instructions_dict
                A :py:class:`typing.Mapping` of value names to format
                instructions.
            values_dicts
                A :py:class:`typing.List` of :py:class:`typing.Mapping`
                of value names to values to be written.

        Raises:
            KeyError: One of the ``values_dicts`` does not contain a key that is
                contained in ``format_instructions_dict``.
        """
        for values_dict in values_dicts:
            self.write_from_format_instructions_dict(
                format_instructions_dict,
                values_dict
            )

    @staticmethod
    def check_dict_matches_format_instructions_dict(
        target_dict: ValuesDict,
        format_instructions_dict: FormatInstructionsDict
    ) -> None:
        """Checks whether a given set of values can validly be passed to
        :any:`write_from_format_instructions_dict` for given format
        instructions.

        Args:
            target_dict
                A :py:class:`typing.Mapping` of value names to values to
                be checked.
            format_instructions_dict
                A :py:class:`typing.Mapping` of value names to format
                instructions.

        Raises:
            UserWarning: A key is missing from ``target_dict`` that is present
                in ``format_instructions_dict``, or the type or struct size of
                a value for a key in ``target_dict`` does not match that
                proscribed for that key in ``format_instructions_dict``.
        """
        for k, v in format_instructions_dict.items():
            if k not in target_dict:
                warnings.warn(k + ' missing')
            elif isinstance(v, str):
                try:
                    struct.pack(v, target_dict[k])
                except struct.error:
                    warnings.warn(
                        k + ' should be formatted as ' + v
                    )
            elif isinstance(v, PascalStyleFormatInstruction):
                if not isinstance(target_dict[k], v.value):
                    warnings.warn(
                        k + ' should be of class ' + str(v.value.__name__)
                    )
            elif isinstance(v, PascalStyleFormatInstructionStringLengthSize):
                if not isinstance(target_dict[k], v.format_instruction.value):
                    warnings.warn(
                        k + ' should be of class ' +
                            str(v.format_instruction.value.__name__)
                    )
            else:
                raise NotImplementedError()


class PascalStyleDict(utils.BaseDict, abc.ABC):
    def __init__(self, params: ValuesDict):
        super().__init__(params)
        self.check_params_are_valid()

    __FORMAT_INSTRUCTIONS_DICT: typing.ClassVar[FormatInstructionsDict] = {}

    @classmethod
    @abc.abstractmethod
    def get_format_instructions_dict(cls) -> FormatInstructionsDict:
        return types.MappingProxyType(
            PascalStyleDict.__FORMAT_INSTRUCTIONS_DICT
        )

    FORMAT_INSTRUCTIONS_DICT = utils.readonly_static_property(
        get_format_instructions_dict
    )

    def check_params_are_valid(self) -> None:
        PascalStyleByteStream.check_dict_matches_format_instructions_dict(
            self.data,
            self.FORMAT_INSTRUCTIONS_DICT
        )
