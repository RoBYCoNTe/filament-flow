<?php

namespace RoBYCoNTe\FilamentFlow\Presentation;

/**
 * How a presented value reads: a line of text, a list of labelled pairs, a table of rows, a
 * set of files — or nothing at all.
 *
 * The shape of `FieldPresentation::$value` follows the format: a string for Text, a map of
 * label => text for Pairs, columns and rows for Table, names and links for Files.
 */
enum FieldFormat: string
{
    case Text = 'text';
    case Pairs = 'pairs';
    case Table = 'table';
    case Files = 'files';
    case Empty = 'empty';
}
