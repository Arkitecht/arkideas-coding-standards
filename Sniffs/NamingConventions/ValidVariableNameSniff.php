<?php

namespace PHP_CodeSniffer\Standards\Arkideas\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractVariableSniff;
use PHP_CodeSniffer\Util\Common;

class ArkIdeas_Sniffs_NamingConventions_ValidVariableNameSniff extends AbstractVariableSniff
{

    /**
     * Tokens to ignore so that we can find a DOUBLE_COLON.
     *
     * @var array
     */
    private $_ignore = [
        T_WHITESPACE,
        T_COMMENT,
    ];

    protected function processVariable(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $varName = ltrim($tokens[ $stackPtr ]['content'], '$');

        $phpReservedVars = [
            '_SERVER',
            '_GET',
            '_POST',
            '_REQUEST',
            '_SESSION',
            '_ENV',
            '_COOKIE',
            '_FILES',
            'GLOBALS',
            'http_response_header',
            'HTTP_RAW_POST_DATA',
            'php_errormsg',
        ];

        // If it's a php reserved var, then its ok.
        if (in_array($varName, $phpReservedVars) === true) {
            return;
        }

        $objOperator = $phpcsFile->findNext([T_WHITESPACE], ($stackPtr + 1), null, true);
        if ($tokens[ $objOperator ]['code'] === T_OBJECT_OPERATOR) {
            // Check to see if we are using a variable from an object.
            $var = $phpcsFile->findNext([T_WHITESPACE], ($objOperator + 1), null, true);
            if ($tokens[ $var ]['code'] === T_STRING) {
                // Either a var name or a function call, so check for bracket.
                $bracket = $phpcsFile->findNext([T_WHITESPACE], ($var + 1), null, true);

                if ($tokens[ $bracket ]['code'] !== T_OPEN_PARENTHESIS) {
                    $objVarName = $tokens[ $var ]['content'];

                    // There is no way for us to know if the var is public or private,
                    // so we have to ignore a leading underscore if there is one and just
                    // check the main part of the variable name.
                    $originalVarName = $objVarName;
                    if (substr($objVarName, 0, 1) === '_') {
                        $objVarName = substr($objVarName, 1);
                    }

                    if (self::isSnakeCase($objVarName) === false) {
                        $error = 'Variable "%s" is not in valid snake case format';
                        $data = [$originalVarName];
                        $phpcsFile->addError($error, $var, 'NotSnakeCase', $data);
                    } else if (preg_match('|\d|', $objVarName) === 1) {
                        $warning = 'Variable "%s" contains numbers but this is discouraged';
                        $data = [$originalVarName];
                        $phpcsFile->addWarning($warning, $stackPtr, 'ContainsNumbers', $data);
                    }
                }//end if
            }//end if
        }//end if

        // There is no way for us to know if the var is public or private,
        // so we have to ignore a leading underscore if there is one and just
        // check the main part of the variable name.
        $originalVarName = $varName;
        if (substr($varName, 0, 1) === '_') {
            $objOperator = $phpcsFile->findPrevious([T_WHITESPACE], ($stackPtr - 1), null, true);
            if ($tokens[ $objOperator ]['code'] === T_DOUBLE_COLON) {
                // The variable lives within a class, and is referenced like
                // this: MyClass::$_variable, so we don't know its scope.
                $inClass = true;
            } else {
                $inClass = $phpcsFile->hasCondition($stackPtr, [T_CLASS, T_INTERFACE, T_TRAIT]);
            }

            if ($inClass === true) {
                $varName = substr($varName, 1);
            }
        } else {
            $staticVariable = false;

            $objOperator = $phpcsFile->findPrevious([T_WHITESPACE], ($stackPtr - 1), null, true);
            if ($tokens[ $objOperator ]['code'] === T_DOUBLE_COLON) {
                $staticVariable = true;
            }

            if ($staticVariable === true) {
                if (self::isSnakeCase($varName) === false) {
                    $error = 'Variable "%s" is not in valid snake case format';
                    $data = [$varName];
                    $phpcsFile->addError($error, $stackPtr, 'NotSnakeCase', $data);
                } else if (preg_match('|\d|', $varName) === 1) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data = [$varName];
                    $phpcsFile->addWarning($warning, $stackPtr, 'ContainsNumbers', $data);
                }
            } else {

                if (Common::isCamelCaps($varName, false, true, false) === false) {
                    $error = 'Variable "%s" is not in valid camel caps format';
                    $data = [$originalVarName];
                    $phpcsFile->addError($error, $stackPtr, 'NotCamelCaps', $data);
                } else if (preg_match('|\d|', $varName) === 1) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data = [$originalVarName];
                    $phpcsFile->addWarning($warning, $stackPtr, 'ContainsNumbers', $data);
                }
            }
        }

    }//end processVariable()


    /**
     * Processes class member variables.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    protected function processMemberVar(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $varName = ltrim($tokens[ $stackPtr ]['content'], '$');
        $memberProps = $phpcsFile->getMemberProperties($stackPtr);
        $public = ($memberProps['scope'] === 'public');

        $laravelVars = [
            'dontReport',
            'primaryKey',
            'redirectTo',
            'middlewareGroups',
            'routeMiddleware',
        ];

        if (in_array($varName, $laravelVars) === true) {
            //Skip some laravel constructs
            return;
        }


        if (substr($varName, 0, 1) === '_') {
            $error = 'Member variable "%s" must not contain a leading underscore';
            $data = [$varName];
            $phpcsFile->addError($error, $stackPtr, 'PublicHasUnderscore', $data);

            return;
        }

        if (self::isSnakeCase($varName) === false) {
            $error = 'Member variable "%s" is not in valid snake case format';
            $data = [$varName];
            $phpcsFile->addError($error, $stackPtr, 'MemberVarNotSnakeCase', $data);
        } else if (preg_match('|\d|', $varName) === 1) {
            $warning = 'Member variable "%s" contains numbers but this is discouraged';
            $data = [$varName];
            $phpcsFile->addWarning($warning, $stackPtr, 'MemberVarContainsNumbers', $data);
        }

    }//end processMemberVar()

    private static function isSnakeCase($varname)
    {
        if ($varname !== strtolower($varname)) {
            return false;
        }

        if (!preg_match('/[a-z0-9_]/', $varname)) {
            return false;
        }

        return true;
    }


    /**
     * Processes the variable found within a double quoted string.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the double quoted
     *                                        string.
     *
     * @return void
     */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $phpReservedVars = [
            '_SERVER',
            '_GET',
            '_POST',
            '_REQUEST',
            '_SESSION',
            '_ENV',
            '_COOKIE',
            '_FILES',
            'GLOBALS',
            'http_response_header',
            'HTTP_RAW_POST_DATA',
            'php_errormsg',
        ];

        if (preg_match_all('|[^\\\]\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|', $tokens[ $stackPtr ]['content'], $matches) !== 0) {
            foreach ($matches[1] as $varName) {
                // If it's a php reserved var, then its ok.
                if (in_array($varName, $phpReservedVars) === true) {
                    continue;
                }

                if (Common::isCamelCaps($varName, false, true, false) === false) {
                    $error = 'Variable "%s" is not in valid camel caps format';
                    $data = [$varName];
                    $phpcsFile->addError($error, $stackPtr, 'StringVarNotCamelCaps', $data);
                } else if (preg_match('|\d|', $varName) === 1) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data = [$varName];
                    $phpcsFile->addWarning($warning, $stackPtr, 'StringVarContainsNumbers', $data);
                }
            }//end foreach
        }//end if

    }//end processVariableInString()


}//end class
