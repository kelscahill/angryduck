/******/ (function(modules) { // webpackBootstrap
/******/ 	function hotDisposeChunk(chunkId) {
/******/ 		delete installedChunks[chunkId];
/******/ 	}
/******/ 	var parentHotUpdateCallback = window["webpackHotUpdate"];
/******/ 	window["webpackHotUpdate"] = 
/******/ 	function webpackHotUpdateCallback(chunkId, moreModules) { // eslint-disable-line no-unused-vars
/******/ 		hotAddUpdateChunk(chunkId, moreModules);
/******/ 		if(parentHotUpdateCallback) parentHotUpdateCallback(chunkId, moreModules);
/******/ 	} ;
/******/ 	
/******/ 	function hotDownloadUpdateChunk(chunkId) { // eslint-disable-line no-unused-vars
/******/ 		var head = document.getElementsByTagName("head")[0];
/******/ 		var script = document.createElement("script");
/******/ 		script.type = "text/javascript";
/******/ 		script.charset = "utf-8";
/******/ 		script.src = __webpack_require__.p + "" + chunkId + "." + hotCurrentHash + ".hot-update.js";
/******/ 		;
/******/ 		head.appendChild(script);
/******/ 	}
/******/ 	
/******/ 	function hotDownloadManifest(requestTimeout) { // eslint-disable-line no-unused-vars
/******/ 		requestTimeout = requestTimeout || 10000;
/******/ 		return new Promise(function(resolve, reject) {
/******/ 			if(typeof XMLHttpRequest === "undefined")
/******/ 				return reject(new Error("No browser support"));
/******/ 			try {
/******/ 				var request = new XMLHttpRequest();
/******/ 				var requestPath = __webpack_require__.p + "" + hotCurrentHash + ".hot-update.json";
/******/ 				request.open("GET", requestPath, true);
/******/ 				request.timeout = requestTimeout;
/******/ 				request.send(null);
/******/ 			} catch(err) {
/******/ 				return reject(err);
/******/ 			}
/******/ 			request.onreadystatechange = function() {
/******/ 				if(request.readyState !== 4) return;
/******/ 				if(request.status === 0) {
/******/ 					// timeout
/******/ 					reject(new Error("Manifest request to " + requestPath + " timed out."));
/******/ 				} else if(request.status === 404) {
/******/ 					// no update available
/******/ 					resolve();
/******/ 				} else if(request.status !== 200 && request.status !== 304) {
/******/ 					// other failure
/******/ 					reject(new Error("Manifest request to " + requestPath + " failed."));
/******/ 				} else {
/******/ 					// success
/******/ 					try {
/******/ 						var update = JSON.parse(request.responseText);
/******/ 					} catch(e) {
/******/ 						reject(e);
/******/ 						return;
/******/ 					}
/******/ 					resolve(update);
/******/ 				}
/******/ 			};
/******/ 		});
/******/ 	}
/******/
/******/ 	
/******/ 	
/******/ 	var hotApplyOnUpdate = true;
/******/ 	var hotCurrentHash = "3bb610809a9b413713dd"; // eslint-disable-line no-unused-vars
/******/ 	var hotRequestTimeout = 10000;
/******/ 	var hotCurrentModuleData = {};
/******/ 	var hotCurrentChildModule; // eslint-disable-line no-unused-vars
/******/ 	var hotCurrentParents = []; // eslint-disable-line no-unused-vars
/******/ 	var hotCurrentParentsTemp = []; // eslint-disable-line no-unused-vars
/******/ 	
/******/ 	function hotCreateRequire(moduleId) { // eslint-disable-line no-unused-vars
/******/ 		var me = installedModules[moduleId];
/******/ 		if(!me) return __webpack_require__;
/******/ 		var fn = function(request) {
/******/ 			if(me.hot.active) {
/******/ 				if(installedModules[request]) {
/******/ 					if(installedModules[request].parents.indexOf(moduleId) < 0)
/******/ 						installedModules[request].parents.push(moduleId);
/******/ 				} else {
/******/ 					hotCurrentParents = [moduleId];
/******/ 					hotCurrentChildModule = request;
/******/ 				}
/******/ 				if(me.children.indexOf(request) < 0)
/******/ 					me.children.push(request);
/******/ 			} else {
/******/ 				console.warn("[HMR] unexpected require(" + request + ") from disposed module " + moduleId);
/******/ 				hotCurrentParents = [];
/******/ 			}
/******/ 			return __webpack_require__(request);
/******/ 		};
/******/ 		var ObjectFactory = function ObjectFactory(name) {
/******/ 			return {
/******/ 				configurable: true,
/******/ 				enumerable: true,
/******/ 				get: function() {
/******/ 					return __webpack_require__[name];
/******/ 				},
/******/ 				set: function(value) {
/******/ 					__webpack_require__[name] = value;
/******/ 				}
/******/ 			};
/******/ 		};
/******/ 		for(var name in __webpack_require__) {
/******/ 			if(Object.prototype.hasOwnProperty.call(__webpack_require__, name) && name !== "e") {
/******/ 				Object.defineProperty(fn, name, ObjectFactory(name));
/******/ 			}
/******/ 		}
/******/ 		fn.e = function(chunkId) {
/******/ 			if(hotStatus === "ready")
/******/ 				hotSetStatus("prepare");
/******/ 			hotChunksLoading++;
/******/ 			return __webpack_require__.e(chunkId).then(finishChunkLoading, function(err) {
/******/ 				finishChunkLoading();
/******/ 				throw err;
/******/ 			});
/******/ 	
/******/ 			function finishChunkLoading() {
/******/ 				hotChunksLoading--;
/******/ 				if(hotStatus === "prepare") {
/******/ 					if(!hotWaitingFilesMap[chunkId]) {
/******/ 						hotEnsureUpdateChunk(chunkId);
/******/ 					}
/******/ 					if(hotChunksLoading === 0 && hotWaitingFiles === 0) {
/******/ 						hotUpdateDownloaded();
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 		return fn;
/******/ 	}
/******/ 	
/******/ 	function hotCreateModule(moduleId) { // eslint-disable-line no-unused-vars
/******/ 		var hot = {
/******/ 			// private stuff
/******/ 			_acceptedDependencies: {},
/******/ 			_declinedDependencies: {},
/******/ 			_selfAccepted: false,
/******/ 			_selfDeclined: false,
/******/ 			_disposeHandlers: [],
/******/ 			_main: hotCurrentChildModule !== moduleId,
/******/ 	
/******/ 			// Module API
/******/ 			active: true,
/******/ 			accept: function(dep, callback) {
/******/ 				if(typeof dep === "undefined")
/******/ 					hot._selfAccepted = true;
/******/ 				else if(typeof dep === "function")
/******/ 					hot._selfAccepted = dep;
/******/ 				else if(typeof dep === "object")
/******/ 					for(var i = 0; i < dep.length; i++)
/******/ 						hot._acceptedDependencies[dep[i]] = callback || function() {};
/******/ 				else
/******/ 					hot._acceptedDependencies[dep] = callback || function() {};
/******/ 			},
/******/ 			decline: function(dep) {
/******/ 				if(typeof dep === "undefined")
/******/ 					hot._selfDeclined = true;
/******/ 				else if(typeof dep === "object")
/******/ 					for(var i = 0; i < dep.length; i++)
/******/ 						hot._declinedDependencies[dep[i]] = true;
/******/ 				else
/******/ 					hot._declinedDependencies[dep] = true;
/******/ 			},
/******/ 			dispose: function(callback) {
/******/ 				hot._disposeHandlers.push(callback);
/******/ 			},
/******/ 			addDisposeHandler: function(callback) {
/******/ 				hot._disposeHandlers.push(callback);
/******/ 			},
/******/ 			removeDisposeHandler: function(callback) {
/******/ 				var idx = hot._disposeHandlers.indexOf(callback);
/******/ 				if(idx >= 0) hot._disposeHandlers.splice(idx, 1);
/******/ 			},
/******/ 	
/******/ 			// Management API
/******/ 			check: hotCheck,
/******/ 			apply: hotApply,
/******/ 			status: function(l) {
/******/ 				if(!l) return hotStatus;
/******/ 				hotStatusHandlers.push(l);
/******/ 			},
/******/ 			addStatusHandler: function(l) {
/******/ 				hotStatusHandlers.push(l);
/******/ 			},
/******/ 			removeStatusHandler: function(l) {
/******/ 				var idx = hotStatusHandlers.indexOf(l);
/******/ 				if(idx >= 0) hotStatusHandlers.splice(idx, 1);
/******/ 			},
/******/ 	
/******/ 			//inherit from previous dispose call
/******/ 			data: hotCurrentModuleData[moduleId]
/******/ 		};
/******/ 		hotCurrentChildModule = undefined;
/******/ 		return hot;
/******/ 	}
/******/ 	
/******/ 	var hotStatusHandlers = [];
/******/ 	var hotStatus = "idle";
/******/ 	
/******/ 	function hotSetStatus(newStatus) {
/******/ 		hotStatus = newStatus;
/******/ 		for(var i = 0; i < hotStatusHandlers.length; i++)
/******/ 			hotStatusHandlers[i].call(null, newStatus);
/******/ 	}
/******/ 	
/******/ 	// while downloading
/******/ 	var hotWaitingFiles = 0;
/******/ 	var hotChunksLoading = 0;
/******/ 	var hotWaitingFilesMap = {};
/******/ 	var hotRequestedFilesMap = {};
/******/ 	var hotAvailableFilesMap = {};
/******/ 	var hotDeferred;
/******/ 	
/******/ 	// The update info
/******/ 	var hotUpdate, hotUpdateNewHash;
/******/ 	
/******/ 	function toModuleId(id) {
/******/ 		var isNumber = (+id) + "" === id;
/******/ 		return isNumber ? +id : id;
/******/ 	}
/******/ 	
/******/ 	function hotCheck(apply) {
/******/ 		if(hotStatus !== "idle") throw new Error("check() is only allowed in idle status");
/******/ 		hotApplyOnUpdate = apply;
/******/ 		hotSetStatus("check");
/******/ 		return hotDownloadManifest(hotRequestTimeout).then(function(update) {
/******/ 			if(!update) {
/******/ 				hotSetStatus("idle");
/******/ 				return null;
/******/ 			}
/******/ 			hotRequestedFilesMap = {};
/******/ 			hotWaitingFilesMap = {};
/******/ 			hotAvailableFilesMap = update.c;
/******/ 			hotUpdateNewHash = update.h;
/******/ 	
/******/ 			hotSetStatus("prepare");
/******/ 			var promise = new Promise(function(resolve, reject) {
/******/ 				hotDeferred = {
/******/ 					resolve: resolve,
/******/ 					reject: reject
/******/ 				};
/******/ 			});
/******/ 			hotUpdate = {};
/******/ 			var chunkId = 0;
/******/ 			{ // eslint-disable-line no-lone-blocks
/******/ 				/*globals chunkId */
/******/ 				hotEnsureUpdateChunk(chunkId);
/******/ 			}
/******/ 			if(hotStatus === "prepare" && hotChunksLoading === 0 && hotWaitingFiles === 0) {
/******/ 				hotUpdateDownloaded();
/******/ 			}
/******/ 			return promise;
/******/ 		});
/******/ 	}
/******/ 	
/******/ 	function hotAddUpdateChunk(chunkId, moreModules) { // eslint-disable-line no-unused-vars
/******/ 		if(!hotAvailableFilesMap[chunkId] || !hotRequestedFilesMap[chunkId])
/******/ 			return;
/******/ 		hotRequestedFilesMap[chunkId] = false;
/******/ 		for(var moduleId in moreModules) {
/******/ 			if(Object.prototype.hasOwnProperty.call(moreModules, moduleId)) {
/******/ 				hotUpdate[moduleId] = moreModules[moduleId];
/******/ 			}
/******/ 		}
/******/ 		if(--hotWaitingFiles === 0 && hotChunksLoading === 0) {
/******/ 			hotUpdateDownloaded();
/******/ 		}
/******/ 	}
/******/ 	
/******/ 	function hotEnsureUpdateChunk(chunkId) {
/******/ 		if(!hotAvailableFilesMap[chunkId]) {
/******/ 			hotWaitingFilesMap[chunkId] = true;
/******/ 		} else {
/******/ 			hotRequestedFilesMap[chunkId] = true;
/******/ 			hotWaitingFiles++;
/******/ 			hotDownloadUpdateChunk(chunkId);
/******/ 		}
/******/ 	}
/******/ 	
/******/ 	function hotUpdateDownloaded() {
/******/ 		hotSetStatus("ready");
/******/ 		var deferred = hotDeferred;
/******/ 		hotDeferred = null;
/******/ 		if(!deferred) return;
/******/ 		if(hotApplyOnUpdate) {
/******/ 			// Wrap deferred object in Promise to mark it as a well-handled Promise to
/******/ 			// avoid triggering uncaught exception warning in Chrome.
/******/ 			// See https://bugs.chromium.org/p/chromium/issues/detail?id=465666
/******/ 			Promise.resolve().then(function() {
/******/ 				return hotApply(hotApplyOnUpdate);
/******/ 			}).then(
/******/ 				function(result) {
/******/ 					deferred.resolve(result);
/******/ 				},
/******/ 				function(err) {
/******/ 					deferred.reject(err);
/******/ 				}
/******/ 			);
/******/ 		} else {
/******/ 			var outdatedModules = [];
/******/ 			for(var id in hotUpdate) {
/******/ 				if(Object.prototype.hasOwnProperty.call(hotUpdate, id)) {
/******/ 					outdatedModules.push(toModuleId(id));
/******/ 				}
/******/ 			}
/******/ 			deferred.resolve(outdatedModules);
/******/ 		}
/******/ 	}
/******/ 	
/******/ 	function hotApply(options) {
/******/ 		if(hotStatus !== "ready") throw new Error("apply() is only allowed in ready status");
/******/ 		options = options || {};
/******/ 	
/******/ 		var cb;
/******/ 		var i;
/******/ 		var j;
/******/ 		var module;
/******/ 		var moduleId;
/******/ 	
/******/ 		function getAffectedStuff(updateModuleId) {
/******/ 			var outdatedModules = [updateModuleId];
/******/ 			var outdatedDependencies = {};
/******/ 	
/******/ 			var queue = outdatedModules.slice().map(function(id) {
/******/ 				return {
/******/ 					chain: [id],
/******/ 					id: id
/******/ 				};
/******/ 			});
/******/ 			while(queue.length > 0) {
/******/ 				var queueItem = queue.pop();
/******/ 				var moduleId = queueItem.id;
/******/ 				var chain = queueItem.chain;
/******/ 				module = installedModules[moduleId];
/******/ 				if(!module || module.hot._selfAccepted)
/******/ 					continue;
/******/ 				if(module.hot._selfDeclined) {
/******/ 					return {
/******/ 						type: "self-declined",
/******/ 						chain: chain,
/******/ 						moduleId: moduleId
/******/ 					};
/******/ 				}
/******/ 				if(module.hot._main) {
/******/ 					return {
/******/ 						type: "unaccepted",
/******/ 						chain: chain,
/******/ 						moduleId: moduleId
/******/ 					};
/******/ 				}
/******/ 				for(var i = 0; i < module.parents.length; i++) {
/******/ 					var parentId = module.parents[i];
/******/ 					var parent = installedModules[parentId];
/******/ 					if(!parent) continue;
/******/ 					if(parent.hot._declinedDependencies[moduleId]) {
/******/ 						return {
/******/ 							type: "declined",
/******/ 							chain: chain.concat([parentId]),
/******/ 							moduleId: moduleId,
/******/ 							parentId: parentId
/******/ 						};
/******/ 					}
/******/ 					if(outdatedModules.indexOf(parentId) >= 0) continue;
/******/ 					if(parent.hot._acceptedDependencies[moduleId]) {
/******/ 						if(!outdatedDependencies[parentId])
/******/ 							outdatedDependencies[parentId] = [];
/******/ 						addAllToSet(outdatedDependencies[parentId], [moduleId]);
/******/ 						continue;
/******/ 					}
/******/ 					delete outdatedDependencies[parentId];
/******/ 					outdatedModules.push(parentId);
/******/ 					queue.push({
/******/ 						chain: chain.concat([parentId]),
/******/ 						id: parentId
/******/ 					});
/******/ 				}
/******/ 			}
/******/ 	
/******/ 			return {
/******/ 				type: "accepted",
/******/ 				moduleId: updateModuleId,
/******/ 				outdatedModules: outdatedModules,
/******/ 				outdatedDependencies: outdatedDependencies
/******/ 			};
/******/ 		}
/******/ 	
/******/ 		function addAllToSet(a, b) {
/******/ 			for(var i = 0; i < b.length; i++) {
/******/ 				var item = b[i];
/******/ 				if(a.indexOf(item) < 0)
/******/ 					a.push(item);
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// at begin all updates modules are outdated
/******/ 		// the "outdated" status can propagate to parents if they don't accept the children
/******/ 		var outdatedDependencies = {};
/******/ 		var outdatedModules = [];
/******/ 		var appliedUpdate = {};
/******/ 	
/******/ 		var warnUnexpectedRequire = function warnUnexpectedRequire() {
/******/ 			console.warn("[HMR] unexpected require(" + result.moduleId + ") to disposed module");
/******/ 		};
/******/ 	
/******/ 		for(var id in hotUpdate) {
/******/ 			if(Object.prototype.hasOwnProperty.call(hotUpdate, id)) {
/******/ 				moduleId = toModuleId(id);
/******/ 				var result;
/******/ 				if(hotUpdate[id]) {
/******/ 					result = getAffectedStuff(moduleId);
/******/ 				} else {
/******/ 					result = {
/******/ 						type: "disposed",
/******/ 						moduleId: id
/******/ 					};
/******/ 				}
/******/ 				var abortError = false;
/******/ 				var doApply = false;
/******/ 				var doDispose = false;
/******/ 				var chainInfo = "";
/******/ 				if(result.chain) {
/******/ 					chainInfo = "\nUpdate propagation: " + result.chain.join(" -> ");
/******/ 				}
/******/ 				switch(result.type) {
/******/ 					case "self-declined":
/******/ 						if(options.onDeclined)
/******/ 							options.onDeclined(result);
/******/ 						if(!options.ignoreDeclined)
/******/ 							abortError = new Error("Aborted because of self decline: " + result.moduleId + chainInfo);
/******/ 						break;
/******/ 					case "declined":
/******/ 						if(options.onDeclined)
/******/ 							options.onDeclined(result);
/******/ 						if(!options.ignoreDeclined)
/******/ 							abortError = new Error("Aborted because of declined dependency: " + result.moduleId + " in " + result.parentId + chainInfo);
/******/ 						break;
/******/ 					case "unaccepted":
/******/ 						if(options.onUnaccepted)
/******/ 							options.onUnaccepted(result);
/******/ 						if(!options.ignoreUnaccepted)
/******/ 							abortError = new Error("Aborted because " + moduleId + " is not accepted" + chainInfo);
/******/ 						break;
/******/ 					case "accepted":
/******/ 						if(options.onAccepted)
/******/ 							options.onAccepted(result);
/******/ 						doApply = true;
/******/ 						break;
/******/ 					case "disposed":
/******/ 						if(options.onDisposed)
/******/ 							options.onDisposed(result);
/******/ 						doDispose = true;
/******/ 						break;
/******/ 					default:
/******/ 						throw new Error("Unexception type " + result.type);
/******/ 				}
/******/ 				if(abortError) {
/******/ 					hotSetStatus("abort");
/******/ 					return Promise.reject(abortError);
/******/ 				}
/******/ 				if(doApply) {
/******/ 					appliedUpdate[moduleId] = hotUpdate[moduleId];
/******/ 					addAllToSet(outdatedModules, result.outdatedModules);
/******/ 					for(moduleId in result.outdatedDependencies) {
/******/ 						if(Object.prototype.hasOwnProperty.call(result.outdatedDependencies, moduleId)) {
/******/ 							if(!outdatedDependencies[moduleId])
/******/ 								outdatedDependencies[moduleId] = [];
/******/ 							addAllToSet(outdatedDependencies[moduleId], result.outdatedDependencies[moduleId]);
/******/ 						}
/******/ 					}
/******/ 				}
/******/ 				if(doDispose) {
/******/ 					addAllToSet(outdatedModules, [result.moduleId]);
/******/ 					appliedUpdate[moduleId] = warnUnexpectedRequire;
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// Store self accepted outdated modules to require them later by the module system
/******/ 		var outdatedSelfAcceptedModules = [];
/******/ 		for(i = 0; i < outdatedModules.length; i++) {
/******/ 			moduleId = outdatedModules[i];
/******/ 			if(installedModules[moduleId] && installedModules[moduleId].hot._selfAccepted)
/******/ 				outdatedSelfAcceptedModules.push({
/******/ 					module: moduleId,
/******/ 					errorHandler: installedModules[moduleId].hot._selfAccepted
/******/ 				});
/******/ 		}
/******/ 	
/******/ 		// Now in "dispose" phase
/******/ 		hotSetStatus("dispose");
/******/ 		Object.keys(hotAvailableFilesMap).forEach(function(chunkId) {
/******/ 			if(hotAvailableFilesMap[chunkId] === false) {
/******/ 				hotDisposeChunk(chunkId);
/******/ 			}
/******/ 		});
/******/ 	
/******/ 		var idx;
/******/ 		var queue = outdatedModules.slice();
/******/ 		while(queue.length > 0) {
/******/ 			moduleId = queue.pop();
/******/ 			module = installedModules[moduleId];
/******/ 			if(!module) continue;
/******/ 	
/******/ 			var data = {};
/******/ 	
/******/ 			// Call dispose handlers
/******/ 			var disposeHandlers = module.hot._disposeHandlers;
/******/ 			for(j = 0; j < disposeHandlers.length; j++) {
/******/ 				cb = disposeHandlers[j];
/******/ 				cb(data);
/******/ 			}
/******/ 			hotCurrentModuleData[moduleId] = data;
/******/ 	
/******/ 			// disable module (this disables requires from this module)
/******/ 			module.hot.active = false;
/******/ 	
/******/ 			// remove module from cache
/******/ 			delete installedModules[moduleId];
/******/ 	
/******/ 			// when disposing there is no need to call dispose handler
/******/ 			delete outdatedDependencies[moduleId];
/******/ 	
/******/ 			// remove "parents" references from all children
/******/ 			for(j = 0; j < module.children.length; j++) {
/******/ 				var child = installedModules[module.children[j]];
/******/ 				if(!child) continue;
/******/ 				idx = child.parents.indexOf(moduleId);
/******/ 				if(idx >= 0) {
/******/ 					child.parents.splice(idx, 1);
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// remove outdated dependency from module children
/******/ 		var dependency;
/******/ 		var moduleOutdatedDependencies;
/******/ 		for(moduleId in outdatedDependencies) {
/******/ 			if(Object.prototype.hasOwnProperty.call(outdatedDependencies, moduleId)) {
/******/ 				module = installedModules[moduleId];
/******/ 				if(module) {
/******/ 					moduleOutdatedDependencies = outdatedDependencies[moduleId];
/******/ 					for(j = 0; j < moduleOutdatedDependencies.length; j++) {
/******/ 						dependency = moduleOutdatedDependencies[j];
/******/ 						idx = module.children.indexOf(dependency);
/******/ 						if(idx >= 0) module.children.splice(idx, 1);
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// Not in "apply" phase
/******/ 		hotSetStatus("apply");
/******/ 	
/******/ 		hotCurrentHash = hotUpdateNewHash;
/******/ 	
/******/ 		// insert new code
/******/ 		for(moduleId in appliedUpdate) {
/******/ 			if(Object.prototype.hasOwnProperty.call(appliedUpdate, moduleId)) {
/******/ 				modules[moduleId] = appliedUpdate[moduleId];
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// call accept handlers
/******/ 		var error = null;
/******/ 		for(moduleId in outdatedDependencies) {
/******/ 			if(Object.prototype.hasOwnProperty.call(outdatedDependencies, moduleId)) {
/******/ 				module = installedModules[moduleId];
/******/ 				if(module) {
/******/ 					moduleOutdatedDependencies = outdatedDependencies[moduleId];
/******/ 					var callbacks = [];
/******/ 					for(i = 0; i < moduleOutdatedDependencies.length; i++) {
/******/ 						dependency = moduleOutdatedDependencies[i];
/******/ 						cb = module.hot._acceptedDependencies[dependency];
/******/ 						if(cb) {
/******/ 							if(callbacks.indexOf(cb) >= 0) continue;
/******/ 							callbacks.push(cb);
/******/ 						}
/******/ 					}
/******/ 					for(i = 0; i < callbacks.length; i++) {
/******/ 						cb = callbacks[i];
/******/ 						try {
/******/ 							cb(moduleOutdatedDependencies);
/******/ 						} catch(err) {
/******/ 							if(options.onErrored) {
/******/ 								options.onErrored({
/******/ 									type: "accept-errored",
/******/ 									moduleId: moduleId,
/******/ 									dependencyId: moduleOutdatedDependencies[i],
/******/ 									error: err
/******/ 								});
/******/ 							}
/******/ 							if(!options.ignoreErrored) {
/******/ 								if(!error)
/******/ 									error = err;
/******/ 							}
/******/ 						}
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// Load self accepted modules
/******/ 		for(i = 0; i < outdatedSelfAcceptedModules.length; i++) {
/******/ 			var item = outdatedSelfAcceptedModules[i];
/******/ 			moduleId = item.module;
/******/ 			hotCurrentParents = [moduleId];
/******/ 			try {
/******/ 				__webpack_require__(moduleId);
/******/ 			} catch(err) {
/******/ 				if(typeof item.errorHandler === "function") {
/******/ 					try {
/******/ 						item.errorHandler(err);
/******/ 					} catch(err2) {
/******/ 						if(options.onErrored) {
/******/ 							options.onErrored({
/******/ 								type: "self-accept-error-handler-errored",
/******/ 								moduleId: moduleId,
/******/ 								error: err2,
/******/ 								orginalError: err, // TODO remove in webpack 4
/******/ 								originalError: err
/******/ 							});
/******/ 						}
/******/ 						if(!options.ignoreErrored) {
/******/ 							if(!error)
/******/ 								error = err2;
/******/ 						}
/******/ 						if(!error)
/******/ 							error = err;
/******/ 					}
/******/ 				} else {
/******/ 					if(options.onErrored) {
/******/ 						options.onErrored({
/******/ 							type: "self-accept-errored",
/******/ 							moduleId: moduleId,
/******/ 							error: err
/******/ 						});
/******/ 					}
/******/ 					if(!options.ignoreErrored) {
/******/ 						if(!error)
/******/ 							error = err;
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 	
/******/ 		// handle errors in accept handlers and self accepted module load
/******/ 		if(error) {
/******/ 			hotSetStatus("fail");
/******/ 			return Promise.reject(error);
/******/ 		}
/******/ 	
/******/ 		hotSetStatus("idle");
/******/ 		return new Promise(function(resolve) {
/******/ 			resolve(outdatedModules);
/******/ 		});
/******/ 	}
/******/
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {},
/******/ 			hot: hotCreateModule(moduleId),
/******/ 			parents: (hotCurrentParentsTemp = hotCurrentParents, hotCurrentParents = [], hotCurrentParentsTemp),
/******/ 			children: []
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, hotCreateRequire(moduleId));
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, {
/******/ 				configurable: false,
/******/ 				enumerable: true,
/******/ 				get: getter
/******/ 			});
/******/ 		}
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "http://localhost:3000/wp-content/themes/angry-duck/dist/";
/******/
/******/ 	// __webpack_hash__
/******/ 	__webpack_require__.h = function() { return hotCurrentHash; };
/******/
/******/ 	// Load entry module and return exports
/******/ 	return hotCreateRequire(17)(__webpack_require__.s = 17);
/******/ })
/************************************************************************/
/******/ ([
/* 0 */
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports) {

module.exports = jQuery;

/***/ }),
/* 1 */
/*!*************************************!*\
  !*** ./build/helpers/hmr-client.js ***!
  \*************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

var hotMiddlewareScript = __webpack_require__(/*! webpack-hot-middleware/client?noInfo=true&timeout=20000&reload=true */ 2);

hotMiddlewareScript.subscribe(function (event) {
  if (event.action === 'reload') {
    window.location.reload();
  }
});


/***/ }),
/* 2 */
/*!********************************************************************************!*\
  !*** (webpack)-hot-middleware/client.js?noInfo=true&timeout=20000&reload=true ***!
  \********************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

/* WEBPACK VAR INJECTION */(function(__resourceQuery, module) {/*eslint-env browser*/
/*global __resourceQuery __webpack_public_path__*/

var options = {
  path: "/__webpack_hmr",
  timeout: 20 * 1000,
  overlay: true,
  reload: false,
  log: true,
  warn: true,
  name: '',
  autoConnect: true,
  overlayStyles: {},
  overlayWarnings: false,
  ansiColors: {}
};
if (true) {
  var querystring = __webpack_require__(/*! querystring */ 4);
  var overrides = querystring.parse(__resourceQuery.slice(1));
  setOverrides(overrides);
}

if (typeof window === 'undefined') {
  // do nothing
} else if (typeof window.EventSource === 'undefined') {
  console.warn(
    "webpack-hot-middleware's client requires EventSource to work. " +
    "You should include a polyfill if you want to support this browser: " +
    "https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events#Tools"
  );
} else {
  if (options.autoConnect) {
    connect();
  }
}

/* istanbul ignore next */
function setOptionsAndConnect(overrides) {
  setOverrides(overrides);
  connect();
}

function setOverrides(overrides) {
  if (overrides.autoConnect) options.autoConnect = overrides.autoConnect == 'true';
  if (overrides.path) options.path = overrides.path;
  if (overrides.timeout) options.timeout = overrides.timeout;
  if (overrides.overlay) options.overlay = overrides.overlay !== 'false';
  if (overrides.reload) options.reload = overrides.reload !== 'false';
  if (overrides.noInfo && overrides.noInfo !== 'false') {
    options.log = false;
  }
  if (overrides.name) {
    options.name = overrides.name;
  }
  if (overrides.quiet && overrides.quiet !== 'false') {
    options.log = false;
    options.warn = false;
  }

  if (overrides.dynamicPublicPath) {
    options.path = __webpack_require__.p + options.path;
  }

  if (overrides.ansiColors) options.ansiColors = JSON.parse(overrides.ansiColors);
  if (overrides.overlayStyles) options.overlayStyles = JSON.parse(overrides.overlayStyles);

  if (overrides.overlayWarnings) {
    options.overlayWarnings = overrides.overlayWarnings == 'true';
  }
}

function EventSourceWrapper() {
  var source;
  var lastActivity = new Date();
  var listeners = [];

  init();
  var timer = setInterval(function() {
    if ((new Date() - lastActivity) > options.timeout) {
      handleDisconnect();
    }
  }, options.timeout / 2);

  function init() {
    source = new window.EventSource(options.path);
    source.onopen = handleOnline;
    source.onerror = handleDisconnect;
    source.onmessage = handleMessage;
  }

  function handleOnline() {
    if (options.log) console.log("[HMR] connected");
    lastActivity = new Date();
  }

  function handleMessage(event) {
    lastActivity = new Date();
    for (var i = 0; i < listeners.length; i++) {
      listeners[i](event);
    }
  }

  function handleDisconnect() {
    clearInterval(timer);
    source.close();
    setTimeout(init, options.timeout);
  }

  return {
    addMessageListener: function(fn) {
      listeners.push(fn);
    }
  };
}

function getEventSourceWrapper() {
  if (!window.__whmEventSourceWrapper) {
    window.__whmEventSourceWrapper = {};
  }
  if (!window.__whmEventSourceWrapper[options.path]) {
    // cache the wrapper for other entries loaded on
    // the same page with the same options.path
    window.__whmEventSourceWrapper[options.path] = EventSourceWrapper();
  }
  return window.__whmEventSourceWrapper[options.path];
}

function connect() {
  getEventSourceWrapper().addMessageListener(handleMessage);

  function handleMessage(event) {
    if (event.data == "\uD83D\uDC93") {
      return;
    }
    try {
      processMessage(JSON.parse(event.data));
    } catch (ex) {
      if (options.warn) {
        console.warn("Invalid HMR message: " + event.data + "\n" + ex);
      }
    }
  }
}

// the reporter needs to be a singleton on the page
// in case the client is being used by multiple bundles
// we only want to report once.
// all the errors will go to all clients
var singletonKey = '__webpack_hot_middleware_reporter__';
var reporter;
if (typeof window !== 'undefined') {
  if (!window[singletonKey]) {
    window[singletonKey] = createReporter();
  }
  reporter = window[singletonKey];
}

function createReporter() {
  var strip = __webpack_require__(/*! strip-ansi */ 7);

  var overlay;
  if (typeof document !== 'undefined' && options.overlay) {
    overlay = __webpack_require__(/*! ./client-overlay */ 9)({
      ansiColors: options.ansiColors,
      overlayStyles: options.overlayStyles
    });
  }

  var styles = {
    errors: "color: #ff0000;",
    warnings: "color: #999933;"
  };
  var previousProblems = null;
  function log(type, obj) {
    var newProblems = obj[type].map(function(msg) { return strip(msg); }).join('\n');
    if (previousProblems == newProblems) {
      return;
    } else {
      previousProblems = newProblems;
    }

    var style = styles[type];
    var name = obj.name ? "'" + obj.name + "' " : "";
    var title = "[HMR] bundle " + name + "has " + obj[type].length + " " + type;
    // NOTE: console.warn or console.error will print the stack trace
    // which isn't helpful here, so using console.log to escape it.
    if (console.group && console.groupEnd) {
      console.group("%c" + title, style);
      console.log("%c" + newProblems, style);
      console.groupEnd();
    } else {
      console.log(
        "%c" + title + "\n\t%c" + newProblems.replace(/\n/g, "\n\t"),
        style + "font-weight: bold;",
        style + "font-weight: normal;"
      );
    }
  }

  return {
    cleanProblemsCache: function () {
      previousProblems = null;
    },
    problems: function(type, obj) {
      if (options.warn) {
        log(type, obj);
      }
      if (overlay) {
        if (options.overlayWarnings || type === 'errors') {
          overlay.showProblems(type, obj[type]);
          return false;
        }
        overlay.clear();
      }
      return true;
    },
    success: function() {
      if (overlay) overlay.clear();
    },
    useCustomOverlay: function(customOverlay) {
      overlay = customOverlay;
    }
  };
}

var processUpdate = __webpack_require__(/*! ./process-update */ 15);

var customHandler;
var subscribeAllHandler;
function processMessage(obj) {
  switch(obj.action) {
    case "building":
      if (options.log) {
        console.log(
          "[HMR] bundle " + (obj.name ? "'" + obj.name + "' " : "") +
          "rebuilding"
        );
      }
      break;
    case "built":
      if (options.log) {
        console.log(
          "[HMR] bundle " + (obj.name ? "'" + obj.name + "' " : "") +
          "rebuilt in " + obj.time + "ms"
        );
      }
      // fall through
    case "sync":
      if (obj.name && options.name && obj.name !== options.name) {
        return;
      }
      var applyUpdate = true;
      if (obj.errors.length > 0) {
        if (reporter) reporter.problems('errors', obj);
        applyUpdate = false;
      } else if (obj.warnings.length > 0) {
        if (reporter) {
          var overlayShown = reporter.problems('warnings', obj);
          applyUpdate = overlayShown;
        }
      } else {
        if (reporter) {
          reporter.cleanProblemsCache();
          reporter.success();
        }
      }
      if (applyUpdate) {
        processUpdate(obj.hash, obj.modules, options);
      }
      break;
    default:
      if (customHandler) {
        customHandler(obj);
      }
  }

  if (subscribeAllHandler) {
    subscribeAllHandler(obj);
  }
}

if (module) {
  module.exports = {
    subscribeAll: function subscribeAll(handler) {
      subscribeAllHandler = handler;
    },
    subscribe: function subscribe(handler) {
      customHandler = handler;
    },
    useCustomOverlay: function useCustomOverlay(customOverlay) {
      if (reporter) reporter.useCustomOverlay(customOverlay);
    },
    setOptionsAndConnect: setOptionsAndConnect
  };
}

/* WEBPACK VAR INJECTION */}.call(exports, "?noInfo=true&timeout=20000&reload=true", __webpack_require__(/*! ./../webpack/buildin/module.js */ 3)(module)))

/***/ }),
/* 3 */
/*!***********************************!*\
  !*** (webpack)/buildin/module.js ***!
  \***********************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports) {

module.exports = function(module) {
	if(!module.webpackPolyfill) {
		module.deprecate = function() {};
		module.paths = [];
		// module.parent = undefined by default
		if(!module.children) module.children = [];
		Object.defineProperty(module, "loaded", {
			enumerable: true,
			get: function() {
				return module.l;
			}
		});
		Object.defineProperty(module, "id", {
			enumerable: true,
			get: function() {
				return module.i;
			}
		});
		module.webpackPolyfill = 1;
	}
	return module;
};


/***/ }),
/* 4 */
/*!***************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/querystring-es3/index.js ***!
  \***************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


exports.decode = exports.parse = __webpack_require__(/*! ./decode */ 5);
exports.encode = exports.stringify = __webpack_require__(/*! ./encode */ 6);


/***/ }),
/* 5 */
/*!****************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/querystring-es3/decode.js ***!
  \****************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.



// If obj.hasOwnProperty has been overridden, then calling
// obj.hasOwnProperty(prop) will break.
// See: https://github.com/joyent/node/issues/1707
function hasOwnProperty(obj, prop) {
  return Object.prototype.hasOwnProperty.call(obj, prop);
}

module.exports = function(qs, sep, eq, options) {
  sep = sep || '&';
  eq = eq || '=';
  var obj = {};

  if (typeof qs !== 'string' || qs.length === 0) {
    return obj;
  }

  var regexp = /\+/g;
  qs = qs.split(sep);

  var maxKeys = 1000;
  if (options && typeof options.maxKeys === 'number') {
    maxKeys = options.maxKeys;
  }

  var len = qs.length;
  // maxKeys <= 0 means that we should not limit keys count
  if (maxKeys > 0 && len > maxKeys) {
    len = maxKeys;
  }

  for (var i = 0; i < len; ++i) {
    var x = qs[i].replace(regexp, '%20'),
        idx = x.indexOf(eq),
        kstr, vstr, k, v;

    if (idx >= 0) {
      kstr = x.substr(0, idx);
      vstr = x.substr(idx + 1);
    } else {
      kstr = x;
      vstr = '';
    }

    k = decodeURIComponent(kstr);
    v = decodeURIComponent(vstr);

    if (!hasOwnProperty(obj, k)) {
      obj[k] = v;
    } else if (isArray(obj[k])) {
      obj[k].push(v);
    } else {
      obj[k] = [obj[k], v];
    }
  }

  return obj;
};

var isArray = Array.isArray || function (xs) {
  return Object.prototype.toString.call(xs) === '[object Array]';
};


/***/ }),
/* 6 */
/*!****************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/querystring-es3/encode.js ***!
  \****************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.



var stringifyPrimitive = function(v) {
  switch (typeof v) {
    case 'string':
      return v;

    case 'boolean':
      return v ? 'true' : 'false';

    case 'number':
      return isFinite(v) ? v : '';

    default:
      return '';
  }
};

module.exports = function(obj, sep, eq, name) {
  sep = sep || '&';
  eq = eq || '=';
  if (obj === null) {
    obj = undefined;
  }

  if (typeof obj === 'object') {
    return map(objectKeys(obj), function(k) {
      var ks = encodeURIComponent(stringifyPrimitive(k)) + eq;
      if (isArray(obj[k])) {
        return map(obj[k], function(v) {
          return ks + encodeURIComponent(stringifyPrimitive(v));
        }).join(sep);
      } else {
        return ks + encodeURIComponent(stringifyPrimitive(obj[k]));
      }
    }).join(sep);

  }

  if (!name) return '';
  return encodeURIComponent(stringifyPrimitive(name)) + eq +
         encodeURIComponent(stringifyPrimitive(obj));
};

var isArray = Array.isArray || function (xs) {
  return Object.prototype.toString.call(xs) === '[object Array]';
};

function map (xs, f) {
  if (xs.map) return xs.map(f);
  var res = [];
  for (var i = 0; i < xs.length; i++) {
    res.push(f(xs[i], i));
  }
  return res;
}

var objectKeys = Object.keys || function (obj) {
  var res = [];
  for (var key in obj) {
    if (Object.prototype.hasOwnProperty.call(obj, key)) res.push(key);
  }
  return res;
};


/***/ }),
/* 7 */
/*!**********************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/strip-ansi/index.js ***!
  \**********************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

var ansiRegex = __webpack_require__(/*! ansi-regex */ 8)();

module.exports = function (str) {
	return typeof str === 'string' ? str.replace(ansiRegex, '') : str;
};


/***/ }),
/* 8 */
/*!**********************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/ansi-regex/index.js ***!
  \**********************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

module.exports = function () {
	return /[\u001b\u009b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-PRZcf-nqry=><]/g;
};


/***/ }),
/* 9 */
/*!**************************************************!*\
  !*** (webpack)-hot-middleware/client-overlay.js ***!
  \**************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

/*eslint-env browser*/

var clientOverlay = document.createElement('div');
clientOverlay.id = 'webpack-hot-middleware-clientOverlay';
var styles = {
  background: 'rgba(0,0,0,0.85)',
  color: '#E8E8E8',
  lineHeight: '1.2',
  whiteSpace: 'pre',
  fontFamily: 'Menlo, Consolas, monospace',
  fontSize: '13px',
  position: 'fixed',
  zIndex: 9999,
  padding: '10px',
  left: 0,
  right: 0,
  top: 0,
  bottom: 0,
  overflow: 'auto',
  dir: 'ltr',
  textAlign: 'left'
};

var ansiHTML = __webpack_require__(/*! ansi-html */ 10);
var colors = {
  reset: ['transparent', 'transparent'],
  black: '181818',
  red: 'E36049',
  green: 'B3CB74',
  yellow: 'FFD080',
  blue: '7CAFC2',
  magenta: '7FACCA',
  cyan: 'C3C2EF',
  lightgrey: 'EBE7E3',
  darkgrey: '6D7891'
};

var Entities = __webpack_require__(/*! html-entities */ 11).AllHtmlEntities;
var entities = new Entities();

function showProblems(type, lines) {
  clientOverlay.innerHTML = '';
  lines.forEach(function(msg) {
    msg = ansiHTML(entities.encode(msg));
    var div = document.createElement('div');
    div.style.marginBottom = '26px';
    div.innerHTML = problemType(type) + ' in ' + msg;
    clientOverlay.appendChild(div);
  });
  if (document.body) {
    document.body.appendChild(clientOverlay);
  }
}

function clear() {
  if (document.body && clientOverlay.parentNode) {
    document.body.removeChild(clientOverlay);
  }
}

function problemType (type) {
  var problemColors = {
    errors: colors.red,
    warnings: colors.yellow
  };
  var color = problemColors[type] || colors.red;
  return (
    '<span style="background-color:#' + color + '; color:#fff; padding:2px 4px; border-radius: 2px">' +
      type.slice(0, -1).toUpperCase() +
    '</span>'
  );
}

module.exports = function(options) {
  for (var color in options.overlayColors) {
    if (color in colors) {
      colors[color] = options.overlayColors[color];
    }
    ansiHTML.setColors(colors);
  }

  for (var style in options.overlayStyles) {
    styles[style] = options.overlayStyles[style];
  }

  for (var key in styles) {
    clientOverlay.style[key] = styles[key];
  }

  return {
    showProblems: showProblems,
    clear: clear
  }
};

module.exports.clear = clear;
module.exports.showProblems = showProblems;


/***/ }),
/* 10 */
/*!*********************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/ansi-html/index.js ***!
  \*********************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


module.exports = ansiHTML

// Reference to https://github.com/sindresorhus/ansi-regex
var _regANSI = /(?:(?:\u001b\[)|\u009b)(?:(?:[0-9]{1,3})?(?:(?:;[0-9]{0,3})*)?[A-M|f-m])|\u001b[A-M]/

var _defColors = {
  reset: ['fff', '000'], // [FOREGROUD_COLOR, BACKGROUND_COLOR]
  black: '000',
  red: 'ff0000',
  green: '209805',
  yellow: 'e8bf03',
  blue: '0000ff',
  magenta: 'ff00ff',
  cyan: '00ffee',
  lightgrey: 'f0f0f0',
  darkgrey: '888'
}
var _styles = {
  30: 'black',
  31: 'red',
  32: 'green',
  33: 'yellow',
  34: 'blue',
  35: 'magenta',
  36: 'cyan',
  37: 'lightgrey'
}
var _openTags = {
  '1': 'font-weight:bold', // bold
  '2': 'opacity:0.5', // dim
  '3': '<i>', // italic
  '4': '<u>', // underscore
  '8': 'display:none', // hidden
  '9': '<del>' // delete
}
var _closeTags = {
  '23': '</i>', // reset italic
  '24': '</u>', // reset underscore
  '29': '</del>' // reset delete
}

;[0, 21, 22, 27, 28, 39, 49].forEach(function (n) {
  _closeTags[n] = '</span>'
})

/**
 * Converts text with ANSI color codes to HTML markup.
 * @param {String} text
 * @returns {*}
 */
function ansiHTML (text) {
  // Returns the text if the string has no ANSI escape code.
  if (!_regANSI.test(text)) {
    return text
  }

  // Cache opened sequence.
  var ansiCodes = []
  // Replace with markup.
  var ret = text.replace(/\033\[(\d+)*m/g, function (match, seq) {
    var ot = _openTags[seq]
    if (ot) {
      // If current sequence has been opened, close it.
      if (!!~ansiCodes.indexOf(seq)) { // eslint-disable-line no-extra-boolean-cast
        ansiCodes.pop()
        return '</span>'
      }
      // Open tag.
      ansiCodes.push(seq)
      return ot[0] === '<' ? ot : '<span style="' + ot + ';">'
    }

    var ct = _closeTags[seq]
    if (ct) {
      // Pop sequence
      ansiCodes.pop()
      return ct
    }
    return ''
  })

  // Make sure tags are closed.
  var l = ansiCodes.length
  ;(l > 0) && (ret += Array(l + 1).join('</span>'))

  return ret
}

/**
 * Customize colors.
 * @param {Object} colors reference to _defColors
 */
ansiHTML.setColors = function (colors) {
  if (typeof colors !== 'object') {
    throw new Error('`colors` parameter must be an Object.')
  }

  var _finalColors = {}
  for (var key in _defColors) {
    var hex = colors.hasOwnProperty(key) ? colors[key] : null
    if (!hex) {
      _finalColors[key] = _defColors[key]
      continue
    }
    if ('reset' === key) {
      if (typeof hex === 'string') {
        hex = [hex]
      }
      if (!Array.isArray(hex) || hex.length === 0 || hex.some(function (h) {
        return typeof h !== 'string'
      })) {
        throw new Error('The value of `' + key + '` property must be an Array and each item could only be a hex string, e.g.: FF0000')
      }
      var defHexColor = _defColors[key]
      if (!hex[0]) {
        hex[0] = defHexColor[0]
      }
      if (hex.length === 1 || !hex[1]) {
        hex = [hex[0]]
        hex.push(defHexColor[1])
      }

      hex = hex.slice(0, 2)
    } else if (typeof hex !== 'string') {
      throw new Error('The value of `' + key + '` property must be a hex string, e.g.: FF0000')
    }
    _finalColors[key] = hex
  }
  _setTags(_finalColors)
}

/**
 * Reset colors.
 */
ansiHTML.reset = function () {
  _setTags(_defColors)
}

/**
 * Expose tags, including open and close.
 * @type {Object}
 */
ansiHTML.tags = {}

if (Object.defineProperty) {
  Object.defineProperty(ansiHTML.tags, 'open', {
    get: function () { return _openTags }
  })
  Object.defineProperty(ansiHTML.tags, 'close', {
    get: function () { return _closeTags }
  })
} else {
  ansiHTML.tags.open = _openTags
  ansiHTML.tags.close = _closeTags
}

function _setTags (colors) {
  // reset all
  _openTags['0'] = 'font-weight:normal;opacity:1;color:#' + colors.reset[0] + ';background:#' + colors.reset[1]
  // inverse
  _openTags['7'] = 'color:#' + colors.reset[1] + ';background:#' + colors.reset[0]
  // dark grey
  _openTags['90'] = 'color:#' + colors.darkgrey

  for (var code in _styles) {
    var color = _styles[code]
    var oriColor = colors[color] || '000'
    _openTags[code] = 'color:#' + oriColor
    code = parseInt(code)
    _openTags[(code + 10).toString()] = 'background:#' + oriColor
  }
}

ansiHTML.reset()


/***/ }),
/* 11 */
/*!*****************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/html-entities/lib/index.js ***!
  \*****************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", { value: true });
var xml_entities_1 = __webpack_require__(/*! ./xml-entities */ 12);
exports.XmlEntities = xml_entities_1.XmlEntities;
var html4_entities_1 = __webpack_require__(/*! ./html4-entities */ 13);
exports.Html4Entities = html4_entities_1.Html4Entities;
var html5_entities_1 = __webpack_require__(/*! ./html5-entities */ 14);
exports.Html5Entities = html5_entities_1.Html5Entities;
exports.AllHtmlEntities = html5_entities_1.Html5Entities;


/***/ }),
/* 12 */
/*!************************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/html-entities/lib/xml-entities.js ***!
  \************************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", { value: true });
var ALPHA_INDEX = {
    '&lt': '<',
    '&gt': '>',
    '&quot': '"',
    '&apos': '\'',
    '&amp': '&',
    '&lt;': '<',
    '&gt;': '>',
    '&quot;': '"',
    '&apos;': '\'',
    '&amp;': '&'
};
var CHAR_INDEX = {
    60: 'lt',
    62: 'gt',
    34: 'quot',
    39: 'apos',
    38: 'amp'
};
var CHAR_S_INDEX = {
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    '\'': '&apos;',
    '&': '&amp;'
};
var XmlEntities = /** @class */ (function () {
    function XmlEntities() {
    }
    XmlEntities.prototype.encode = function (str) {
        if (!str || !str.length) {
            return '';
        }
        return str.replace(/[<>"'&]/g, function (s) {
            return CHAR_S_INDEX[s];
        });
    };
    XmlEntities.encode = function (str) {
        return new XmlEntities().encode(str);
    };
    XmlEntities.prototype.decode = function (str) {
        if (!str || !str.length) {
            return '';
        }
        return str.replace(/&#?[0-9a-zA-Z]+;?/g, function (s) {
            if (s.charAt(1) === '#') {
                var code = s.charAt(2).toLowerCase() === 'x' ?
                    parseInt(s.substr(3), 16) :
                    parseInt(s.substr(2));
                if (isNaN(code) || code < -32768 || code > 65535) {
                    return '';
                }
                return String.fromCharCode(code);
            }
            return ALPHA_INDEX[s] || s;
        });
    };
    XmlEntities.decode = function (str) {
        return new XmlEntities().decode(str);
    };
    XmlEntities.prototype.encodeNonUTF = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var c = str.charCodeAt(i);
            var alpha = CHAR_INDEX[c];
            if (alpha) {
                result += "&" + alpha + ";";
                i++;
                continue;
            }
            if (c < 32 || c > 126) {
                result += '&#' + c + ';';
            }
            else {
                result += str.charAt(i);
            }
            i++;
        }
        return result;
    };
    XmlEntities.encodeNonUTF = function (str) {
        return new XmlEntities().encodeNonUTF(str);
    };
    XmlEntities.prototype.encodeNonASCII = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLenght = str.length;
        var result = '';
        var i = 0;
        while (i < strLenght) {
            var c = str.charCodeAt(i);
            if (c <= 255) {
                result += str[i++];
                continue;
            }
            result += '&#' + c + ';';
            i++;
        }
        return result;
    };
    XmlEntities.encodeNonASCII = function (str) {
        return new XmlEntities().encodeNonASCII(str);
    };
    return XmlEntities;
}());
exports.XmlEntities = XmlEntities;


/***/ }),
/* 13 */
/*!**************************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/html-entities/lib/html4-entities.js ***!
  \**************************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", { value: true });
var HTML_ALPHA = ['apos', 'nbsp', 'iexcl', 'cent', 'pound', 'curren', 'yen', 'brvbar', 'sect', 'uml', 'copy', 'ordf', 'laquo', 'not', 'shy', 'reg', 'macr', 'deg', 'plusmn', 'sup2', 'sup3', 'acute', 'micro', 'para', 'middot', 'cedil', 'sup1', 'ordm', 'raquo', 'frac14', 'frac12', 'frac34', 'iquest', 'Agrave', 'Aacute', 'Acirc', 'Atilde', 'Auml', 'Aring', 'Aelig', 'Ccedil', 'Egrave', 'Eacute', 'Ecirc', 'Euml', 'Igrave', 'Iacute', 'Icirc', 'Iuml', 'ETH', 'Ntilde', 'Ograve', 'Oacute', 'Ocirc', 'Otilde', 'Ouml', 'times', 'Oslash', 'Ugrave', 'Uacute', 'Ucirc', 'Uuml', 'Yacute', 'THORN', 'szlig', 'agrave', 'aacute', 'acirc', 'atilde', 'auml', 'aring', 'aelig', 'ccedil', 'egrave', 'eacute', 'ecirc', 'euml', 'igrave', 'iacute', 'icirc', 'iuml', 'eth', 'ntilde', 'ograve', 'oacute', 'ocirc', 'otilde', 'ouml', 'divide', 'oslash', 'ugrave', 'uacute', 'ucirc', 'uuml', 'yacute', 'thorn', 'yuml', 'quot', 'amp', 'lt', 'gt', 'OElig', 'oelig', 'Scaron', 'scaron', 'Yuml', 'circ', 'tilde', 'ensp', 'emsp', 'thinsp', 'zwnj', 'zwj', 'lrm', 'rlm', 'ndash', 'mdash', 'lsquo', 'rsquo', 'sbquo', 'ldquo', 'rdquo', 'bdquo', 'dagger', 'Dagger', 'permil', 'lsaquo', 'rsaquo', 'euro', 'fnof', 'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi', 'Rho', 'Sigma', 'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega', 'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta', 'iota', 'kappa', 'lambda', 'mu', 'nu', 'xi', 'omicron', 'pi', 'rho', 'sigmaf', 'sigma', 'tau', 'upsilon', 'phi', 'chi', 'psi', 'omega', 'thetasym', 'upsih', 'piv', 'bull', 'hellip', 'prime', 'Prime', 'oline', 'frasl', 'weierp', 'image', 'real', 'trade', 'alefsym', 'larr', 'uarr', 'rarr', 'darr', 'harr', 'crarr', 'lArr', 'uArr', 'rArr', 'dArr', 'hArr', 'forall', 'part', 'exist', 'empty', 'nabla', 'isin', 'notin', 'ni', 'prod', 'sum', 'minus', 'lowast', 'radic', 'prop', 'infin', 'ang', 'and', 'or', 'cap', 'cup', 'int', 'there4', 'sim', 'cong', 'asymp', 'ne', 'equiv', 'le', 'ge', 'sub', 'sup', 'nsub', 'sube', 'supe', 'oplus', 'otimes', 'perp', 'sdot', 'lceil', 'rceil', 'lfloor', 'rfloor', 'lang', 'rang', 'loz', 'spades', 'clubs', 'hearts', 'diams'];
var HTML_CODES = [39, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 172, 173, 174, 175, 176, 177, 178, 179, 180, 181, 182, 183, 184, 185, 186, 187, 188, 189, 190, 191, 192, 193, 194, 195, 196, 197, 198, 199, 200, 201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217, 218, 219, 220, 221, 222, 223, 224, 225, 226, 227, 228, 229, 230, 231, 232, 233, 234, 235, 236, 237, 238, 239, 240, 241, 242, 243, 244, 245, 246, 247, 248, 249, 250, 251, 252, 253, 254, 255, 34, 38, 60, 62, 338, 339, 352, 353, 376, 710, 732, 8194, 8195, 8201, 8204, 8205, 8206, 8207, 8211, 8212, 8216, 8217, 8218, 8220, 8221, 8222, 8224, 8225, 8240, 8249, 8250, 8364, 402, 913, 914, 915, 916, 917, 918, 919, 920, 921, 922, 923, 924, 925, 926, 927, 928, 929, 931, 932, 933, 934, 935, 936, 937, 945, 946, 947, 948, 949, 950, 951, 952, 953, 954, 955, 956, 957, 958, 959, 960, 961, 962, 963, 964, 965, 966, 967, 968, 969, 977, 978, 982, 8226, 8230, 8242, 8243, 8254, 8260, 8472, 8465, 8476, 8482, 8501, 8592, 8593, 8594, 8595, 8596, 8629, 8656, 8657, 8658, 8659, 8660, 8704, 8706, 8707, 8709, 8711, 8712, 8713, 8715, 8719, 8721, 8722, 8727, 8730, 8733, 8734, 8736, 8743, 8744, 8745, 8746, 8747, 8756, 8764, 8773, 8776, 8800, 8801, 8804, 8805, 8834, 8835, 8836, 8838, 8839, 8853, 8855, 8869, 8901, 8968, 8969, 8970, 8971, 9001, 9002, 9674, 9824, 9827, 9829, 9830];
var alphaIndex = {};
var numIndex = {};
(function () {
    var i = 0;
    var length = HTML_ALPHA.length;
    while (i < length) {
        var a = HTML_ALPHA[i];
        var c = HTML_CODES[i];
        alphaIndex[a] = String.fromCharCode(c);
        numIndex[c] = a;
        i++;
    }
})();
var Html4Entities = /** @class */ (function () {
    function Html4Entities() {
    }
    Html4Entities.prototype.decode = function (str) {
        if (!str || !str.length) {
            return '';
        }
        return str.replace(/&(#?[\w\d]+);?/g, function (s, entity) {
            var chr;
            if (entity.charAt(0) === "#") {
                var code = entity.charAt(1).toLowerCase() === 'x' ?
                    parseInt(entity.substr(2), 16) :
                    parseInt(entity.substr(1));
                if (!(isNaN(code) || code < -32768 || code > 65535)) {
                    chr = String.fromCharCode(code);
                }
            }
            else {
                chr = alphaIndex[entity];
            }
            return chr || s;
        });
    };
    Html4Entities.decode = function (str) {
        return new Html4Entities().decode(str);
    };
    Html4Entities.prototype.encode = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var alpha = numIndex[str.charCodeAt(i)];
            result += alpha ? "&" + alpha + ";" : str.charAt(i);
            i++;
        }
        return result;
    };
    Html4Entities.encode = function (str) {
        return new Html4Entities().encode(str);
    };
    Html4Entities.prototype.encodeNonUTF = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var cc = str.charCodeAt(i);
            var alpha = numIndex[cc];
            if (alpha) {
                result += "&" + alpha + ";";
            }
            else if (cc < 32 || cc > 126) {
                result += "&#" + cc + ";";
            }
            else {
                result += str.charAt(i);
            }
            i++;
        }
        return result;
    };
    Html4Entities.encodeNonUTF = function (str) {
        return new Html4Entities().encodeNonUTF(str);
    };
    Html4Entities.prototype.encodeNonASCII = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var c = str.charCodeAt(i);
            if (c <= 255) {
                result += str[i++];
                continue;
            }
            result += '&#' + c + ';';
            i++;
        }
        return result;
    };
    Html4Entities.encodeNonASCII = function (str) {
        return new Html4Entities().encodeNonASCII(str);
    };
    return Html4Entities;
}());
exports.Html4Entities = Html4Entities;


/***/ }),
/* 14 */
/*!**************************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/html-entities/lib/html5-entities.js ***!
  \**************************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", { value: true });
var ENTITIES = [['Aacute', [193]], ['aacute', [225]], ['Abreve', [258]], ['abreve', [259]], ['ac', [8766]], ['acd', [8767]], ['acE', [8766, 819]], ['Acirc', [194]], ['acirc', [226]], ['acute', [180]], ['Acy', [1040]], ['acy', [1072]], ['AElig', [198]], ['aelig', [230]], ['af', [8289]], ['Afr', [120068]], ['afr', [120094]], ['Agrave', [192]], ['agrave', [224]], ['alefsym', [8501]], ['aleph', [8501]], ['Alpha', [913]], ['alpha', [945]], ['Amacr', [256]], ['amacr', [257]], ['amalg', [10815]], ['amp', [38]], ['AMP', [38]], ['andand', [10837]], ['And', [10835]], ['and', [8743]], ['andd', [10844]], ['andslope', [10840]], ['andv', [10842]], ['ang', [8736]], ['ange', [10660]], ['angle', [8736]], ['angmsdaa', [10664]], ['angmsdab', [10665]], ['angmsdac', [10666]], ['angmsdad', [10667]], ['angmsdae', [10668]], ['angmsdaf', [10669]], ['angmsdag', [10670]], ['angmsdah', [10671]], ['angmsd', [8737]], ['angrt', [8735]], ['angrtvb', [8894]], ['angrtvbd', [10653]], ['angsph', [8738]], ['angst', [197]], ['angzarr', [9084]], ['Aogon', [260]], ['aogon', [261]], ['Aopf', [120120]], ['aopf', [120146]], ['apacir', [10863]], ['ap', [8776]], ['apE', [10864]], ['ape', [8778]], ['apid', [8779]], ['apos', [39]], ['ApplyFunction', [8289]], ['approx', [8776]], ['approxeq', [8778]], ['Aring', [197]], ['aring', [229]], ['Ascr', [119964]], ['ascr', [119990]], ['Assign', [8788]], ['ast', [42]], ['asymp', [8776]], ['asympeq', [8781]], ['Atilde', [195]], ['atilde', [227]], ['Auml', [196]], ['auml', [228]], ['awconint', [8755]], ['awint', [10769]], ['backcong', [8780]], ['backepsilon', [1014]], ['backprime', [8245]], ['backsim', [8765]], ['backsimeq', [8909]], ['Backslash', [8726]], ['Barv', [10983]], ['barvee', [8893]], ['barwed', [8965]], ['Barwed', [8966]], ['barwedge', [8965]], ['bbrk', [9141]], ['bbrktbrk', [9142]], ['bcong', [8780]], ['Bcy', [1041]], ['bcy', [1073]], ['bdquo', [8222]], ['becaus', [8757]], ['because', [8757]], ['Because', [8757]], ['bemptyv', [10672]], ['bepsi', [1014]], ['bernou', [8492]], ['Bernoullis', [8492]], ['Beta', [914]], ['beta', [946]], ['beth', [8502]], ['between', [8812]], ['Bfr', [120069]], ['bfr', [120095]], ['bigcap', [8898]], ['bigcirc', [9711]], ['bigcup', [8899]], ['bigodot', [10752]], ['bigoplus', [10753]], ['bigotimes', [10754]], ['bigsqcup', [10758]], ['bigstar', [9733]], ['bigtriangledown', [9661]], ['bigtriangleup', [9651]], ['biguplus', [10756]], ['bigvee', [8897]], ['bigwedge', [8896]], ['bkarow', [10509]], ['blacklozenge', [10731]], ['blacksquare', [9642]], ['blacktriangle', [9652]], ['blacktriangledown', [9662]], ['blacktriangleleft', [9666]], ['blacktriangleright', [9656]], ['blank', [9251]], ['blk12', [9618]], ['blk14', [9617]], ['blk34', [9619]], ['block', [9608]], ['bne', [61, 8421]], ['bnequiv', [8801, 8421]], ['bNot', [10989]], ['bnot', [8976]], ['Bopf', [120121]], ['bopf', [120147]], ['bot', [8869]], ['bottom', [8869]], ['bowtie', [8904]], ['boxbox', [10697]], ['boxdl', [9488]], ['boxdL', [9557]], ['boxDl', [9558]], ['boxDL', [9559]], ['boxdr', [9484]], ['boxdR', [9554]], ['boxDr', [9555]], ['boxDR', [9556]], ['boxh', [9472]], ['boxH', [9552]], ['boxhd', [9516]], ['boxHd', [9572]], ['boxhD', [9573]], ['boxHD', [9574]], ['boxhu', [9524]], ['boxHu', [9575]], ['boxhU', [9576]], ['boxHU', [9577]], ['boxminus', [8863]], ['boxplus', [8862]], ['boxtimes', [8864]], ['boxul', [9496]], ['boxuL', [9563]], ['boxUl', [9564]], ['boxUL', [9565]], ['boxur', [9492]], ['boxuR', [9560]], ['boxUr', [9561]], ['boxUR', [9562]], ['boxv', [9474]], ['boxV', [9553]], ['boxvh', [9532]], ['boxvH', [9578]], ['boxVh', [9579]], ['boxVH', [9580]], ['boxvl', [9508]], ['boxvL', [9569]], ['boxVl', [9570]], ['boxVL', [9571]], ['boxvr', [9500]], ['boxvR', [9566]], ['boxVr', [9567]], ['boxVR', [9568]], ['bprime', [8245]], ['breve', [728]], ['Breve', [728]], ['brvbar', [166]], ['bscr', [119991]], ['Bscr', [8492]], ['bsemi', [8271]], ['bsim', [8765]], ['bsime', [8909]], ['bsolb', [10693]], ['bsol', [92]], ['bsolhsub', [10184]], ['bull', [8226]], ['bullet', [8226]], ['bump', [8782]], ['bumpE', [10926]], ['bumpe', [8783]], ['Bumpeq', [8782]], ['bumpeq', [8783]], ['Cacute', [262]], ['cacute', [263]], ['capand', [10820]], ['capbrcup', [10825]], ['capcap', [10827]], ['cap', [8745]], ['Cap', [8914]], ['capcup', [10823]], ['capdot', [10816]], ['CapitalDifferentialD', [8517]], ['caps', [8745, 65024]], ['caret', [8257]], ['caron', [711]], ['Cayleys', [8493]], ['ccaps', [10829]], ['Ccaron', [268]], ['ccaron', [269]], ['Ccedil', [199]], ['ccedil', [231]], ['Ccirc', [264]], ['ccirc', [265]], ['Cconint', [8752]], ['ccups', [10828]], ['ccupssm', [10832]], ['Cdot', [266]], ['cdot', [267]], ['cedil', [184]], ['Cedilla', [184]], ['cemptyv', [10674]], ['cent', [162]], ['centerdot', [183]], ['CenterDot', [183]], ['cfr', [120096]], ['Cfr', [8493]], ['CHcy', [1063]], ['chcy', [1095]], ['check', [10003]], ['checkmark', [10003]], ['Chi', [935]], ['chi', [967]], ['circ', [710]], ['circeq', [8791]], ['circlearrowleft', [8634]], ['circlearrowright', [8635]], ['circledast', [8859]], ['circledcirc', [8858]], ['circleddash', [8861]], ['CircleDot', [8857]], ['circledR', [174]], ['circledS', [9416]], ['CircleMinus', [8854]], ['CirclePlus', [8853]], ['CircleTimes', [8855]], ['cir', [9675]], ['cirE', [10691]], ['cire', [8791]], ['cirfnint', [10768]], ['cirmid', [10991]], ['cirscir', [10690]], ['ClockwiseContourIntegral', [8754]], ['clubs', [9827]], ['clubsuit', [9827]], ['colon', [58]], ['Colon', [8759]], ['Colone', [10868]], ['colone', [8788]], ['coloneq', [8788]], ['comma', [44]], ['commat', [64]], ['comp', [8705]], ['compfn', [8728]], ['complement', [8705]], ['complexes', [8450]], ['cong', [8773]], ['congdot', [10861]], ['Congruent', [8801]], ['conint', [8750]], ['Conint', [8751]], ['ContourIntegral', [8750]], ['copf', [120148]], ['Copf', [8450]], ['coprod', [8720]], ['Coproduct', [8720]], ['copy', [169]], ['COPY', [169]], ['copysr', [8471]], ['CounterClockwiseContourIntegral', [8755]], ['crarr', [8629]], ['cross', [10007]], ['Cross', [10799]], ['Cscr', [119966]], ['cscr', [119992]], ['csub', [10959]], ['csube', [10961]], ['csup', [10960]], ['csupe', [10962]], ['ctdot', [8943]], ['cudarrl', [10552]], ['cudarrr', [10549]], ['cuepr', [8926]], ['cuesc', [8927]], ['cularr', [8630]], ['cularrp', [10557]], ['cupbrcap', [10824]], ['cupcap', [10822]], ['CupCap', [8781]], ['cup', [8746]], ['Cup', [8915]], ['cupcup', [10826]], ['cupdot', [8845]], ['cupor', [10821]], ['cups', [8746, 65024]], ['curarr', [8631]], ['curarrm', [10556]], ['curlyeqprec', [8926]], ['curlyeqsucc', [8927]], ['curlyvee', [8910]], ['curlywedge', [8911]], ['curren', [164]], ['curvearrowleft', [8630]], ['curvearrowright', [8631]], ['cuvee', [8910]], ['cuwed', [8911]], ['cwconint', [8754]], ['cwint', [8753]], ['cylcty', [9005]], ['dagger', [8224]], ['Dagger', [8225]], ['daleth', [8504]], ['darr', [8595]], ['Darr', [8609]], ['dArr', [8659]], ['dash', [8208]], ['Dashv', [10980]], ['dashv', [8867]], ['dbkarow', [10511]], ['dblac', [733]], ['Dcaron', [270]], ['dcaron', [271]], ['Dcy', [1044]], ['dcy', [1076]], ['ddagger', [8225]], ['ddarr', [8650]], ['DD', [8517]], ['dd', [8518]], ['DDotrahd', [10513]], ['ddotseq', [10871]], ['deg', [176]], ['Del', [8711]], ['Delta', [916]], ['delta', [948]], ['demptyv', [10673]], ['dfisht', [10623]], ['Dfr', [120071]], ['dfr', [120097]], ['dHar', [10597]], ['dharl', [8643]], ['dharr', [8642]], ['DiacriticalAcute', [180]], ['DiacriticalDot', [729]], ['DiacriticalDoubleAcute', [733]], ['DiacriticalGrave', [96]], ['DiacriticalTilde', [732]], ['diam', [8900]], ['diamond', [8900]], ['Diamond', [8900]], ['diamondsuit', [9830]], ['diams', [9830]], ['die', [168]], ['DifferentialD', [8518]], ['digamma', [989]], ['disin', [8946]], ['div', [247]], ['divide', [247]], ['divideontimes', [8903]], ['divonx', [8903]], ['DJcy', [1026]], ['djcy', [1106]], ['dlcorn', [8990]], ['dlcrop', [8973]], ['dollar', [36]], ['Dopf', [120123]], ['dopf', [120149]], ['Dot', [168]], ['dot', [729]], ['DotDot', [8412]], ['doteq', [8784]], ['doteqdot', [8785]], ['DotEqual', [8784]], ['dotminus', [8760]], ['dotplus', [8724]], ['dotsquare', [8865]], ['doublebarwedge', [8966]], ['DoubleContourIntegral', [8751]], ['DoubleDot', [168]], ['DoubleDownArrow', [8659]], ['DoubleLeftArrow', [8656]], ['DoubleLeftRightArrow', [8660]], ['DoubleLeftTee', [10980]], ['DoubleLongLeftArrow', [10232]], ['DoubleLongLeftRightArrow', [10234]], ['DoubleLongRightArrow', [10233]], ['DoubleRightArrow', [8658]], ['DoubleRightTee', [8872]], ['DoubleUpArrow', [8657]], ['DoubleUpDownArrow', [8661]], ['DoubleVerticalBar', [8741]], ['DownArrowBar', [10515]], ['downarrow', [8595]], ['DownArrow', [8595]], ['Downarrow', [8659]], ['DownArrowUpArrow', [8693]], ['DownBreve', [785]], ['downdownarrows', [8650]], ['downharpoonleft', [8643]], ['downharpoonright', [8642]], ['DownLeftRightVector', [10576]], ['DownLeftTeeVector', [10590]], ['DownLeftVectorBar', [10582]], ['DownLeftVector', [8637]], ['DownRightTeeVector', [10591]], ['DownRightVectorBar', [10583]], ['DownRightVector', [8641]], ['DownTeeArrow', [8615]], ['DownTee', [8868]], ['drbkarow', [10512]], ['drcorn', [8991]], ['drcrop', [8972]], ['Dscr', [119967]], ['dscr', [119993]], ['DScy', [1029]], ['dscy', [1109]], ['dsol', [10742]], ['Dstrok', [272]], ['dstrok', [273]], ['dtdot', [8945]], ['dtri', [9663]], ['dtrif', [9662]], ['duarr', [8693]], ['duhar', [10607]], ['dwangle', [10662]], ['DZcy', [1039]], ['dzcy', [1119]], ['dzigrarr', [10239]], ['Eacute', [201]], ['eacute', [233]], ['easter', [10862]], ['Ecaron', [282]], ['ecaron', [283]], ['Ecirc', [202]], ['ecirc', [234]], ['ecir', [8790]], ['ecolon', [8789]], ['Ecy', [1069]], ['ecy', [1101]], ['eDDot', [10871]], ['Edot', [278]], ['edot', [279]], ['eDot', [8785]], ['ee', [8519]], ['efDot', [8786]], ['Efr', [120072]], ['efr', [120098]], ['eg', [10906]], ['Egrave', [200]], ['egrave', [232]], ['egs', [10902]], ['egsdot', [10904]], ['el', [10905]], ['Element', [8712]], ['elinters', [9191]], ['ell', [8467]], ['els', [10901]], ['elsdot', [10903]], ['Emacr', [274]], ['emacr', [275]], ['empty', [8709]], ['emptyset', [8709]], ['EmptySmallSquare', [9723]], ['emptyv', [8709]], ['EmptyVerySmallSquare', [9643]], ['emsp13', [8196]], ['emsp14', [8197]], ['emsp', [8195]], ['ENG', [330]], ['eng', [331]], ['ensp', [8194]], ['Eogon', [280]], ['eogon', [281]], ['Eopf', [120124]], ['eopf', [120150]], ['epar', [8917]], ['eparsl', [10723]], ['eplus', [10865]], ['epsi', [949]], ['Epsilon', [917]], ['epsilon', [949]], ['epsiv', [1013]], ['eqcirc', [8790]], ['eqcolon', [8789]], ['eqsim', [8770]], ['eqslantgtr', [10902]], ['eqslantless', [10901]], ['Equal', [10869]], ['equals', [61]], ['EqualTilde', [8770]], ['equest', [8799]], ['Equilibrium', [8652]], ['equiv', [8801]], ['equivDD', [10872]], ['eqvparsl', [10725]], ['erarr', [10609]], ['erDot', [8787]], ['escr', [8495]], ['Escr', [8496]], ['esdot', [8784]], ['Esim', [10867]], ['esim', [8770]], ['Eta', [919]], ['eta', [951]], ['ETH', [208]], ['eth', [240]], ['Euml', [203]], ['euml', [235]], ['euro', [8364]], ['excl', [33]], ['exist', [8707]], ['Exists', [8707]], ['expectation', [8496]], ['exponentiale', [8519]], ['ExponentialE', [8519]], ['fallingdotseq', [8786]], ['Fcy', [1060]], ['fcy', [1092]], ['female', [9792]], ['ffilig', [64259]], ['fflig', [64256]], ['ffllig', [64260]], ['Ffr', [120073]], ['ffr', [120099]], ['filig', [64257]], ['FilledSmallSquare', [9724]], ['FilledVerySmallSquare', [9642]], ['fjlig', [102, 106]], ['flat', [9837]], ['fllig', [64258]], ['fltns', [9649]], ['fnof', [402]], ['Fopf', [120125]], ['fopf', [120151]], ['forall', [8704]], ['ForAll', [8704]], ['fork', [8916]], ['forkv', [10969]], ['Fouriertrf', [8497]], ['fpartint', [10765]], ['frac12', [189]], ['frac13', [8531]], ['frac14', [188]], ['frac15', [8533]], ['frac16', [8537]], ['frac18', [8539]], ['frac23', [8532]], ['frac25', [8534]], ['frac34', [190]], ['frac35', [8535]], ['frac38', [8540]], ['frac45', [8536]], ['frac56', [8538]], ['frac58', [8541]], ['frac78', [8542]], ['frasl', [8260]], ['frown', [8994]], ['fscr', [119995]], ['Fscr', [8497]], ['gacute', [501]], ['Gamma', [915]], ['gamma', [947]], ['Gammad', [988]], ['gammad', [989]], ['gap', [10886]], ['Gbreve', [286]], ['gbreve', [287]], ['Gcedil', [290]], ['Gcirc', [284]], ['gcirc', [285]], ['Gcy', [1043]], ['gcy', [1075]], ['Gdot', [288]], ['gdot', [289]], ['ge', [8805]], ['gE', [8807]], ['gEl', [10892]], ['gel', [8923]], ['geq', [8805]], ['geqq', [8807]], ['geqslant', [10878]], ['gescc', [10921]], ['ges', [10878]], ['gesdot', [10880]], ['gesdoto', [10882]], ['gesdotol', [10884]], ['gesl', [8923, 65024]], ['gesles', [10900]], ['Gfr', [120074]], ['gfr', [120100]], ['gg', [8811]], ['Gg', [8921]], ['ggg', [8921]], ['gimel', [8503]], ['GJcy', [1027]], ['gjcy', [1107]], ['gla', [10917]], ['gl', [8823]], ['glE', [10898]], ['glj', [10916]], ['gnap', [10890]], ['gnapprox', [10890]], ['gne', [10888]], ['gnE', [8809]], ['gneq', [10888]], ['gneqq', [8809]], ['gnsim', [8935]], ['Gopf', [120126]], ['gopf', [120152]], ['grave', [96]], ['GreaterEqual', [8805]], ['GreaterEqualLess', [8923]], ['GreaterFullEqual', [8807]], ['GreaterGreater', [10914]], ['GreaterLess', [8823]], ['GreaterSlantEqual', [10878]], ['GreaterTilde', [8819]], ['Gscr', [119970]], ['gscr', [8458]], ['gsim', [8819]], ['gsime', [10894]], ['gsiml', [10896]], ['gtcc', [10919]], ['gtcir', [10874]], ['gt', [62]], ['GT', [62]], ['Gt', [8811]], ['gtdot', [8919]], ['gtlPar', [10645]], ['gtquest', [10876]], ['gtrapprox', [10886]], ['gtrarr', [10616]], ['gtrdot', [8919]], ['gtreqless', [8923]], ['gtreqqless', [10892]], ['gtrless', [8823]], ['gtrsim', [8819]], ['gvertneqq', [8809, 65024]], ['gvnE', [8809, 65024]], ['Hacek', [711]], ['hairsp', [8202]], ['half', [189]], ['hamilt', [8459]], ['HARDcy', [1066]], ['hardcy', [1098]], ['harrcir', [10568]], ['harr', [8596]], ['hArr', [8660]], ['harrw', [8621]], ['Hat', [94]], ['hbar', [8463]], ['Hcirc', [292]], ['hcirc', [293]], ['hearts', [9829]], ['heartsuit', [9829]], ['hellip', [8230]], ['hercon', [8889]], ['hfr', [120101]], ['Hfr', [8460]], ['HilbertSpace', [8459]], ['hksearow', [10533]], ['hkswarow', [10534]], ['hoarr', [8703]], ['homtht', [8763]], ['hookleftarrow', [8617]], ['hookrightarrow', [8618]], ['hopf', [120153]], ['Hopf', [8461]], ['horbar', [8213]], ['HorizontalLine', [9472]], ['hscr', [119997]], ['Hscr', [8459]], ['hslash', [8463]], ['Hstrok', [294]], ['hstrok', [295]], ['HumpDownHump', [8782]], ['HumpEqual', [8783]], ['hybull', [8259]], ['hyphen', [8208]], ['Iacute', [205]], ['iacute', [237]], ['ic', [8291]], ['Icirc', [206]], ['icirc', [238]], ['Icy', [1048]], ['icy', [1080]], ['Idot', [304]], ['IEcy', [1045]], ['iecy', [1077]], ['iexcl', [161]], ['iff', [8660]], ['ifr', [120102]], ['Ifr', [8465]], ['Igrave', [204]], ['igrave', [236]], ['ii', [8520]], ['iiiint', [10764]], ['iiint', [8749]], ['iinfin', [10716]], ['iiota', [8489]], ['IJlig', [306]], ['ijlig', [307]], ['Imacr', [298]], ['imacr', [299]], ['image', [8465]], ['ImaginaryI', [8520]], ['imagline', [8464]], ['imagpart', [8465]], ['imath', [305]], ['Im', [8465]], ['imof', [8887]], ['imped', [437]], ['Implies', [8658]], ['incare', [8453]], ['in', [8712]], ['infin', [8734]], ['infintie', [10717]], ['inodot', [305]], ['intcal', [8890]], ['int', [8747]], ['Int', [8748]], ['integers', [8484]], ['Integral', [8747]], ['intercal', [8890]], ['Intersection', [8898]], ['intlarhk', [10775]], ['intprod', [10812]], ['InvisibleComma', [8291]], ['InvisibleTimes', [8290]], ['IOcy', [1025]], ['iocy', [1105]], ['Iogon', [302]], ['iogon', [303]], ['Iopf', [120128]], ['iopf', [120154]], ['Iota', [921]], ['iota', [953]], ['iprod', [10812]], ['iquest', [191]], ['iscr', [119998]], ['Iscr', [8464]], ['isin', [8712]], ['isindot', [8949]], ['isinE', [8953]], ['isins', [8948]], ['isinsv', [8947]], ['isinv', [8712]], ['it', [8290]], ['Itilde', [296]], ['itilde', [297]], ['Iukcy', [1030]], ['iukcy', [1110]], ['Iuml', [207]], ['iuml', [239]], ['Jcirc', [308]], ['jcirc', [309]], ['Jcy', [1049]], ['jcy', [1081]], ['Jfr', [120077]], ['jfr', [120103]], ['jmath', [567]], ['Jopf', [120129]], ['jopf', [120155]], ['Jscr', [119973]], ['jscr', [119999]], ['Jsercy', [1032]], ['jsercy', [1112]], ['Jukcy', [1028]], ['jukcy', [1108]], ['Kappa', [922]], ['kappa', [954]], ['kappav', [1008]], ['Kcedil', [310]], ['kcedil', [311]], ['Kcy', [1050]], ['kcy', [1082]], ['Kfr', [120078]], ['kfr', [120104]], ['kgreen', [312]], ['KHcy', [1061]], ['khcy', [1093]], ['KJcy', [1036]], ['kjcy', [1116]], ['Kopf', [120130]], ['kopf', [120156]], ['Kscr', [119974]], ['kscr', [120000]], ['lAarr', [8666]], ['Lacute', [313]], ['lacute', [314]], ['laemptyv', [10676]], ['lagran', [8466]], ['Lambda', [923]], ['lambda', [955]], ['lang', [10216]], ['Lang', [10218]], ['langd', [10641]], ['langle', [10216]], ['lap', [10885]], ['Laplacetrf', [8466]], ['laquo', [171]], ['larrb', [8676]], ['larrbfs', [10527]], ['larr', [8592]], ['Larr', [8606]], ['lArr', [8656]], ['larrfs', [10525]], ['larrhk', [8617]], ['larrlp', [8619]], ['larrpl', [10553]], ['larrsim', [10611]], ['larrtl', [8610]], ['latail', [10521]], ['lAtail', [10523]], ['lat', [10923]], ['late', [10925]], ['lates', [10925, 65024]], ['lbarr', [10508]], ['lBarr', [10510]], ['lbbrk', [10098]], ['lbrace', [123]], ['lbrack', [91]], ['lbrke', [10635]], ['lbrksld', [10639]], ['lbrkslu', [10637]], ['Lcaron', [317]], ['lcaron', [318]], ['Lcedil', [315]], ['lcedil', [316]], ['lceil', [8968]], ['lcub', [123]], ['Lcy', [1051]], ['lcy', [1083]], ['ldca', [10550]], ['ldquo', [8220]], ['ldquor', [8222]], ['ldrdhar', [10599]], ['ldrushar', [10571]], ['ldsh', [8626]], ['le', [8804]], ['lE', [8806]], ['LeftAngleBracket', [10216]], ['LeftArrowBar', [8676]], ['leftarrow', [8592]], ['LeftArrow', [8592]], ['Leftarrow', [8656]], ['LeftArrowRightArrow', [8646]], ['leftarrowtail', [8610]], ['LeftCeiling', [8968]], ['LeftDoubleBracket', [10214]], ['LeftDownTeeVector', [10593]], ['LeftDownVectorBar', [10585]], ['LeftDownVector', [8643]], ['LeftFloor', [8970]], ['leftharpoondown', [8637]], ['leftharpoonup', [8636]], ['leftleftarrows', [8647]], ['leftrightarrow', [8596]], ['LeftRightArrow', [8596]], ['Leftrightarrow', [8660]], ['leftrightarrows', [8646]], ['leftrightharpoons', [8651]], ['leftrightsquigarrow', [8621]], ['LeftRightVector', [10574]], ['LeftTeeArrow', [8612]], ['LeftTee', [8867]], ['LeftTeeVector', [10586]], ['leftthreetimes', [8907]], ['LeftTriangleBar', [10703]], ['LeftTriangle', [8882]], ['LeftTriangleEqual', [8884]], ['LeftUpDownVector', [10577]], ['LeftUpTeeVector', [10592]], ['LeftUpVectorBar', [10584]], ['LeftUpVector', [8639]], ['LeftVectorBar', [10578]], ['LeftVector', [8636]], ['lEg', [10891]], ['leg', [8922]], ['leq', [8804]], ['leqq', [8806]], ['leqslant', [10877]], ['lescc', [10920]], ['les', [10877]], ['lesdot', [10879]], ['lesdoto', [10881]], ['lesdotor', [10883]], ['lesg', [8922, 65024]], ['lesges', [10899]], ['lessapprox', [10885]], ['lessdot', [8918]], ['lesseqgtr', [8922]], ['lesseqqgtr', [10891]], ['LessEqualGreater', [8922]], ['LessFullEqual', [8806]], ['LessGreater', [8822]], ['lessgtr', [8822]], ['LessLess', [10913]], ['lesssim', [8818]], ['LessSlantEqual', [10877]], ['LessTilde', [8818]], ['lfisht', [10620]], ['lfloor', [8970]], ['Lfr', [120079]], ['lfr', [120105]], ['lg', [8822]], ['lgE', [10897]], ['lHar', [10594]], ['lhard', [8637]], ['lharu', [8636]], ['lharul', [10602]], ['lhblk', [9604]], ['LJcy', [1033]], ['ljcy', [1113]], ['llarr', [8647]], ['ll', [8810]], ['Ll', [8920]], ['llcorner', [8990]], ['Lleftarrow', [8666]], ['llhard', [10603]], ['lltri', [9722]], ['Lmidot', [319]], ['lmidot', [320]], ['lmoustache', [9136]], ['lmoust', [9136]], ['lnap', [10889]], ['lnapprox', [10889]], ['lne', [10887]], ['lnE', [8808]], ['lneq', [10887]], ['lneqq', [8808]], ['lnsim', [8934]], ['loang', [10220]], ['loarr', [8701]], ['lobrk', [10214]], ['longleftarrow', [10229]], ['LongLeftArrow', [10229]], ['Longleftarrow', [10232]], ['longleftrightarrow', [10231]], ['LongLeftRightArrow', [10231]], ['Longleftrightarrow', [10234]], ['longmapsto', [10236]], ['longrightarrow', [10230]], ['LongRightArrow', [10230]], ['Longrightarrow', [10233]], ['looparrowleft', [8619]], ['looparrowright', [8620]], ['lopar', [10629]], ['Lopf', [120131]], ['lopf', [120157]], ['loplus', [10797]], ['lotimes', [10804]], ['lowast', [8727]], ['lowbar', [95]], ['LowerLeftArrow', [8601]], ['LowerRightArrow', [8600]], ['loz', [9674]], ['lozenge', [9674]], ['lozf', [10731]], ['lpar', [40]], ['lparlt', [10643]], ['lrarr', [8646]], ['lrcorner', [8991]], ['lrhar', [8651]], ['lrhard', [10605]], ['lrm', [8206]], ['lrtri', [8895]], ['lsaquo', [8249]], ['lscr', [120001]], ['Lscr', [8466]], ['lsh', [8624]], ['Lsh', [8624]], ['lsim', [8818]], ['lsime', [10893]], ['lsimg', [10895]], ['lsqb', [91]], ['lsquo', [8216]], ['lsquor', [8218]], ['Lstrok', [321]], ['lstrok', [322]], ['ltcc', [10918]], ['ltcir', [10873]], ['lt', [60]], ['LT', [60]], ['Lt', [8810]], ['ltdot', [8918]], ['lthree', [8907]], ['ltimes', [8905]], ['ltlarr', [10614]], ['ltquest', [10875]], ['ltri', [9667]], ['ltrie', [8884]], ['ltrif', [9666]], ['ltrPar', [10646]], ['lurdshar', [10570]], ['luruhar', [10598]], ['lvertneqq', [8808, 65024]], ['lvnE', [8808, 65024]], ['macr', [175]], ['male', [9794]], ['malt', [10016]], ['maltese', [10016]], ['Map', [10501]], ['map', [8614]], ['mapsto', [8614]], ['mapstodown', [8615]], ['mapstoleft', [8612]], ['mapstoup', [8613]], ['marker', [9646]], ['mcomma', [10793]], ['Mcy', [1052]], ['mcy', [1084]], ['mdash', [8212]], ['mDDot', [8762]], ['measuredangle', [8737]], ['MediumSpace', [8287]], ['Mellintrf', [8499]], ['Mfr', [120080]], ['mfr', [120106]], ['mho', [8487]], ['micro', [181]], ['midast', [42]], ['midcir', [10992]], ['mid', [8739]], ['middot', [183]], ['minusb', [8863]], ['minus', [8722]], ['minusd', [8760]], ['minusdu', [10794]], ['MinusPlus', [8723]], ['mlcp', [10971]], ['mldr', [8230]], ['mnplus', [8723]], ['models', [8871]], ['Mopf', [120132]], ['mopf', [120158]], ['mp', [8723]], ['mscr', [120002]], ['Mscr', [8499]], ['mstpos', [8766]], ['Mu', [924]], ['mu', [956]], ['multimap', [8888]], ['mumap', [8888]], ['nabla', [8711]], ['Nacute', [323]], ['nacute', [324]], ['nang', [8736, 8402]], ['nap', [8777]], ['napE', [10864, 824]], ['napid', [8779, 824]], ['napos', [329]], ['napprox', [8777]], ['natural', [9838]], ['naturals', [8469]], ['natur', [9838]], ['nbsp', [160]], ['nbump', [8782, 824]], ['nbumpe', [8783, 824]], ['ncap', [10819]], ['Ncaron', [327]], ['ncaron', [328]], ['Ncedil', [325]], ['ncedil', [326]], ['ncong', [8775]], ['ncongdot', [10861, 824]], ['ncup', [10818]], ['Ncy', [1053]], ['ncy', [1085]], ['ndash', [8211]], ['nearhk', [10532]], ['nearr', [8599]], ['neArr', [8663]], ['nearrow', [8599]], ['ne', [8800]], ['nedot', [8784, 824]], ['NegativeMediumSpace', [8203]], ['NegativeThickSpace', [8203]], ['NegativeThinSpace', [8203]], ['NegativeVeryThinSpace', [8203]], ['nequiv', [8802]], ['nesear', [10536]], ['nesim', [8770, 824]], ['NestedGreaterGreater', [8811]], ['NestedLessLess', [8810]], ['nexist', [8708]], ['nexists', [8708]], ['Nfr', [120081]], ['nfr', [120107]], ['ngE', [8807, 824]], ['nge', [8817]], ['ngeq', [8817]], ['ngeqq', [8807, 824]], ['ngeqslant', [10878, 824]], ['nges', [10878, 824]], ['nGg', [8921, 824]], ['ngsim', [8821]], ['nGt', [8811, 8402]], ['ngt', [8815]], ['ngtr', [8815]], ['nGtv', [8811, 824]], ['nharr', [8622]], ['nhArr', [8654]], ['nhpar', [10994]], ['ni', [8715]], ['nis', [8956]], ['nisd', [8954]], ['niv', [8715]], ['NJcy', [1034]], ['njcy', [1114]], ['nlarr', [8602]], ['nlArr', [8653]], ['nldr', [8229]], ['nlE', [8806, 824]], ['nle', [8816]], ['nleftarrow', [8602]], ['nLeftarrow', [8653]], ['nleftrightarrow', [8622]], ['nLeftrightarrow', [8654]], ['nleq', [8816]], ['nleqq', [8806, 824]], ['nleqslant', [10877, 824]], ['nles', [10877, 824]], ['nless', [8814]], ['nLl', [8920, 824]], ['nlsim', [8820]], ['nLt', [8810, 8402]], ['nlt', [8814]], ['nltri', [8938]], ['nltrie', [8940]], ['nLtv', [8810, 824]], ['nmid', [8740]], ['NoBreak', [8288]], ['NonBreakingSpace', [160]], ['nopf', [120159]], ['Nopf', [8469]], ['Not', [10988]], ['not', [172]], ['NotCongruent', [8802]], ['NotCupCap', [8813]], ['NotDoubleVerticalBar', [8742]], ['NotElement', [8713]], ['NotEqual', [8800]], ['NotEqualTilde', [8770, 824]], ['NotExists', [8708]], ['NotGreater', [8815]], ['NotGreaterEqual', [8817]], ['NotGreaterFullEqual', [8807, 824]], ['NotGreaterGreater', [8811, 824]], ['NotGreaterLess', [8825]], ['NotGreaterSlantEqual', [10878, 824]], ['NotGreaterTilde', [8821]], ['NotHumpDownHump', [8782, 824]], ['NotHumpEqual', [8783, 824]], ['notin', [8713]], ['notindot', [8949, 824]], ['notinE', [8953, 824]], ['notinva', [8713]], ['notinvb', [8951]], ['notinvc', [8950]], ['NotLeftTriangleBar', [10703, 824]], ['NotLeftTriangle', [8938]], ['NotLeftTriangleEqual', [8940]], ['NotLess', [8814]], ['NotLessEqual', [8816]], ['NotLessGreater', [8824]], ['NotLessLess', [8810, 824]], ['NotLessSlantEqual', [10877, 824]], ['NotLessTilde', [8820]], ['NotNestedGreaterGreater', [10914, 824]], ['NotNestedLessLess', [10913, 824]], ['notni', [8716]], ['notniva', [8716]], ['notnivb', [8958]], ['notnivc', [8957]], ['NotPrecedes', [8832]], ['NotPrecedesEqual', [10927, 824]], ['NotPrecedesSlantEqual', [8928]], ['NotReverseElement', [8716]], ['NotRightTriangleBar', [10704, 824]], ['NotRightTriangle', [8939]], ['NotRightTriangleEqual', [8941]], ['NotSquareSubset', [8847, 824]], ['NotSquareSubsetEqual', [8930]], ['NotSquareSuperset', [8848, 824]], ['NotSquareSupersetEqual', [8931]], ['NotSubset', [8834, 8402]], ['NotSubsetEqual', [8840]], ['NotSucceeds', [8833]], ['NotSucceedsEqual', [10928, 824]], ['NotSucceedsSlantEqual', [8929]], ['NotSucceedsTilde', [8831, 824]], ['NotSuperset', [8835, 8402]], ['NotSupersetEqual', [8841]], ['NotTilde', [8769]], ['NotTildeEqual', [8772]], ['NotTildeFullEqual', [8775]], ['NotTildeTilde', [8777]], ['NotVerticalBar', [8740]], ['nparallel', [8742]], ['npar', [8742]], ['nparsl', [11005, 8421]], ['npart', [8706, 824]], ['npolint', [10772]], ['npr', [8832]], ['nprcue', [8928]], ['nprec', [8832]], ['npreceq', [10927, 824]], ['npre', [10927, 824]], ['nrarrc', [10547, 824]], ['nrarr', [8603]], ['nrArr', [8655]], ['nrarrw', [8605, 824]], ['nrightarrow', [8603]], ['nRightarrow', [8655]], ['nrtri', [8939]], ['nrtrie', [8941]], ['nsc', [8833]], ['nsccue', [8929]], ['nsce', [10928, 824]], ['Nscr', [119977]], ['nscr', [120003]], ['nshortmid', [8740]], ['nshortparallel', [8742]], ['nsim', [8769]], ['nsime', [8772]], ['nsimeq', [8772]], ['nsmid', [8740]], ['nspar', [8742]], ['nsqsube', [8930]], ['nsqsupe', [8931]], ['nsub', [8836]], ['nsubE', [10949, 824]], ['nsube', [8840]], ['nsubset', [8834, 8402]], ['nsubseteq', [8840]], ['nsubseteqq', [10949, 824]], ['nsucc', [8833]], ['nsucceq', [10928, 824]], ['nsup', [8837]], ['nsupE', [10950, 824]], ['nsupe', [8841]], ['nsupset', [8835, 8402]], ['nsupseteq', [8841]], ['nsupseteqq', [10950, 824]], ['ntgl', [8825]], ['Ntilde', [209]], ['ntilde', [241]], ['ntlg', [8824]], ['ntriangleleft', [8938]], ['ntrianglelefteq', [8940]], ['ntriangleright', [8939]], ['ntrianglerighteq', [8941]], ['Nu', [925]], ['nu', [957]], ['num', [35]], ['numero', [8470]], ['numsp', [8199]], ['nvap', [8781, 8402]], ['nvdash', [8876]], ['nvDash', [8877]], ['nVdash', [8878]], ['nVDash', [8879]], ['nvge', [8805, 8402]], ['nvgt', [62, 8402]], ['nvHarr', [10500]], ['nvinfin', [10718]], ['nvlArr', [10498]], ['nvle', [8804, 8402]], ['nvlt', [60, 8402]], ['nvltrie', [8884, 8402]], ['nvrArr', [10499]], ['nvrtrie', [8885, 8402]], ['nvsim', [8764, 8402]], ['nwarhk', [10531]], ['nwarr', [8598]], ['nwArr', [8662]], ['nwarrow', [8598]], ['nwnear', [10535]], ['Oacute', [211]], ['oacute', [243]], ['oast', [8859]], ['Ocirc', [212]], ['ocirc', [244]], ['ocir', [8858]], ['Ocy', [1054]], ['ocy', [1086]], ['odash', [8861]], ['Odblac', [336]], ['odblac', [337]], ['odiv', [10808]], ['odot', [8857]], ['odsold', [10684]], ['OElig', [338]], ['oelig', [339]], ['ofcir', [10687]], ['Ofr', [120082]], ['ofr', [120108]], ['ogon', [731]], ['Ograve', [210]], ['ograve', [242]], ['ogt', [10689]], ['ohbar', [10677]], ['ohm', [937]], ['oint', [8750]], ['olarr', [8634]], ['olcir', [10686]], ['olcross', [10683]], ['oline', [8254]], ['olt', [10688]], ['Omacr', [332]], ['omacr', [333]], ['Omega', [937]], ['omega', [969]], ['Omicron', [927]], ['omicron', [959]], ['omid', [10678]], ['ominus', [8854]], ['Oopf', [120134]], ['oopf', [120160]], ['opar', [10679]], ['OpenCurlyDoubleQuote', [8220]], ['OpenCurlyQuote', [8216]], ['operp', [10681]], ['oplus', [8853]], ['orarr', [8635]], ['Or', [10836]], ['or', [8744]], ['ord', [10845]], ['order', [8500]], ['orderof', [8500]], ['ordf', [170]], ['ordm', [186]], ['origof', [8886]], ['oror', [10838]], ['orslope', [10839]], ['orv', [10843]], ['oS', [9416]], ['Oscr', [119978]], ['oscr', [8500]], ['Oslash', [216]], ['oslash', [248]], ['osol', [8856]], ['Otilde', [213]], ['otilde', [245]], ['otimesas', [10806]], ['Otimes', [10807]], ['otimes', [8855]], ['Ouml', [214]], ['ouml', [246]], ['ovbar', [9021]], ['OverBar', [8254]], ['OverBrace', [9182]], ['OverBracket', [9140]], ['OverParenthesis', [9180]], ['para', [182]], ['parallel', [8741]], ['par', [8741]], ['parsim', [10995]], ['parsl', [11005]], ['part', [8706]], ['PartialD', [8706]], ['Pcy', [1055]], ['pcy', [1087]], ['percnt', [37]], ['period', [46]], ['permil', [8240]], ['perp', [8869]], ['pertenk', [8241]], ['Pfr', [120083]], ['pfr', [120109]], ['Phi', [934]], ['phi', [966]], ['phiv', [981]], ['phmmat', [8499]], ['phone', [9742]], ['Pi', [928]], ['pi', [960]], ['pitchfork', [8916]], ['piv', [982]], ['planck', [8463]], ['planckh', [8462]], ['plankv', [8463]], ['plusacir', [10787]], ['plusb', [8862]], ['pluscir', [10786]], ['plus', [43]], ['plusdo', [8724]], ['plusdu', [10789]], ['pluse', [10866]], ['PlusMinus', [177]], ['plusmn', [177]], ['plussim', [10790]], ['plustwo', [10791]], ['pm', [177]], ['Poincareplane', [8460]], ['pointint', [10773]], ['popf', [120161]], ['Popf', [8473]], ['pound', [163]], ['prap', [10935]], ['Pr', [10939]], ['pr', [8826]], ['prcue', [8828]], ['precapprox', [10935]], ['prec', [8826]], ['preccurlyeq', [8828]], ['Precedes', [8826]], ['PrecedesEqual', [10927]], ['PrecedesSlantEqual', [8828]], ['PrecedesTilde', [8830]], ['preceq', [10927]], ['precnapprox', [10937]], ['precneqq', [10933]], ['precnsim', [8936]], ['pre', [10927]], ['prE', [10931]], ['precsim', [8830]], ['prime', [8242]], ['Prime', [8243]], ['primes', [8473]], ['prnap', [10937]], ['prnE', [10933]], ['prnsim', [8936]], ['prod', [8719]], ['Product', [8719]], ['profalar', [9006]], ['profline', [8978]], ['profsurf', [8979]], ['prop', [8733]], ['Proportional', [8733]], ['Proportion', [8759]], ['propto', [8733]], ['prsim', [8830]], ['prurel', [8880]], ['Pscr', [119979]], ['pscr', [120005]], ['Psi', [936]], ['psi', [968]], ['puncsp', [8200]], ['Qfr', [120084]], ['qfr', [120110]], ['qint', [10764]], ['qopf', [120162]], ['Qopf', [8474]], ['qprime', [8279]], ['Qscr', [119980]], ['qscr', [120006]], ['quaternions', [8461]], ['quatint', [10774]], ['quest', [63]], ['questeq', [8799]], ['quot', [34]], ['QUOT', [34]], ['rAarr', [8667]], ['race', [8765, 817]], ['Racute', [340]], ['racute', [341]], ['radic', [8730]], ['raemptyv', [10675]], ['rang', [10217]], ['Rang', [10219]], ['rangd', [10642]], ['range', [10661]], ['rangle', [10217]], ['raquo', [187]], ['rarrap', [10613]], ['rarrb', [8677]], ['rarrbfs', [10528]], ['rarrc', [10547]], ['rarr', [8594]], ['Rarr', [8608]], ['rArr', [8658]], ['rarrfs', [10526]], ['rarrhk', [8618]], ['rarrlp', [8620]], ['rarrpl', [10565]], ['rarrsim', [10612]], ['Rarrtl', [10518]], ['rarrtl', [8611]], ['rarrw', [8605]], ['ratail', [10522]], ['rAtail', [10524]], ['ratio', [8758]], ['rationals', [8474]], ['rbarr', [10509]], ['rBarr', [10511]], ['RBarr', [10512]], ['rbbrk', [10099]], ['rbrace', [125]], ['rbrack', [93]], ['rbrke', [10636]], ['rbrksld', [10638]], ['rbrkslu', [10640]], ['Rcaron', [344]], ['rcaron', [345]], ['Rcedil', [342]], ['rcedil', [343]], ['rceil', [8969]], ['rcub', [125]], ['Rcy', [1056]], ['rcy', [1088]], ['rdca', [10551]], ['rdldhar', [10601]], ['rdquo', [8221]], ['rdquor', [8221]], ['CloseCurlyDoubleQuote', [8221]], ['rdsh', [8627]], ['real', [8476]], ['realine', [8475]], ['realpart', [8476]], ['reals', [8477]], ['Re', [8476]], ['rect', [9645]], ['reg', [174]], ['REG', [174]], ['ReverseElement', [8715]], ['ReverseEquilibrium', [8651]], ['ReverseUpEquilibrium', [10607]], ['rfisht', [10621]], ['rfloor', [8971]], ['rfr', [120111]], ['Rfr', [8476]], ['rHar', [10596]], ['rhard', [8641]], ['rharu', [8640]], ['rharul', [10604]], ['Rho', [929]], ['rho', [961]], ['rhov', [1009]], ['RightAngleBracket', [10217]], ['RightArrowBar', [8677]], ['rightarrow', [8594]], ['RightArrow', [8594]], ['Rightarrow', [8658]], ['RightArrowLeftArrow', [8644]], ['rightarrowtail', [8611]], ['RightCeiling', [8969]], ['RightDoubleBracket', [10215]], ['RightDownTeeVector', [10589]], ['RightDownVectorBar', [10581]], ['RightDownVector', [8642]], ['RightFloor', [8971]], ['rightharpoondown', [8641]], ['rightharpoonup', [8640]], ['rightleftarrows', [8644]], ['rightleftharpoons', [8652]], ['rightrightarrows', [8649]], ['rightsquigarrow', [8605]], ['RightTeeArrow', [8614]], ['RightTee', [8866]], ['RightTeeVector', [10587]], ['rightthreetimes', [8908]], ['RightTriangleBar', [10704]], ['RightTriangle', [8883]], ['RightTriangleEqual', [8885]], ['RightUpDownVector', [10575]], ['RightUpTeeVector', [10588]], ['RightUpVectorBar', [10580]], ['RightUpVector', [8638]], ['RightVectorBar', [10579]], ['RightVector', [8640]], ['ring', [730]], ['risingdotseq', [8787]], ['rlarr', [8644]], ['rlhar', [8652]], ['rlm', [8207]], ['rmoustache', [9137]], ['rmoust', [9137]], ['rnmid', [10990]], ['roang', [10221]], ['roarr', [8702]], ['robrk', [10215]], ['ropar', [10630]], ['ropf', [120163]], ['Ropf', [8477]], ['roplus', [10798]], ['rotimes', [10805]], ['RoundImplies', [10608]], ['rpar', [41]], ['rpargt', [10644]], ['rppolint', [10770]], ['rrarr', [8649]], ['Rrightarrow', [8667]], ['rsaquo', [8250]], ['rscr', [120007]], ['Rscr', [8475]], ['rsh', [8625]], ['Rsh', [8625]], ['rsqb', [93]], ['rsquo', [8217]], ['rsquor', [8217]], ['CloseCurlyQuote', [8217]], ['rthree', [8908]], ['rtimes', [8906]], ['rtri', [9657]], ['rtrie', [8885]], ['rtrif', [9656]], ['rtriltri', [10702]], ['RuleDelayed', [10740]], ['ruluhar', [10600]], ['rx', [8478]], ['Sacute', [346]], ['sacute', [347]], ['sbquo', [8218]], ['scap', [10936]], ['Scaron', [352]], ['scaron', [353]], ['Sc', [10940]], ['sc', [8827]], ['sccue', [8829]], ['sce', [10928]], ['scE', [10932]], ['Scedil', [350]], ['scedil', [351]], ['Scirc', [348]], ['scirc', [349]], ['scnap', [10938]], ['scnE', [10934]], ['scnsim', [8937]], ['scpolint', [10771]], ['scsim', [8831]], ['Scy', [1057]], ['scy', [1089]], ['sdotb', [8865]], ['sdot', [8901]], ['sdote', [10854]], ['searhk', [10533]], ['searr', [8600]], ['seArr', [8664]], ['searrow', [8600]], ['sect', [167]], ['semi', [59]], ['seswar', [10537]], ['setminus', [8726]], ['setmn', [8726]], ['sext', [10038]], ['Sfr', [120086]], ['sfr', [120112]], ['sfrown', [8994]], ['sharp', [9839]], ['SHCHcy', [1065]], ['shchcy', [1097]], ['SHcy', [1064]], ['shcy', [1096]], ['ShortDownArrow', [8595]], ['ShortLeftArrow', [8592]], ['shortmid', [8739]], ['shortparallel', [8741]], ['ShortRightArrow', [8594]], ['ShortUpArrow', [8593]], ['shy', [173]], ['Sigma', [931]], ['sigma', [963]], ['sigmaf', [962]], ['sigmav', [962]], ['sim', [8764]], ['simdot', [10858]], ['sime', [8771]], ['simeq', [8771]], ['simg', [10910]], ['simgE', [10912]], ['siml', [10909]], ['simlE', [10911]], ['simne', [8774]], ['simplus', [10788]], ['simrarr', [10610]], ['slarr', [8592]], ['SmallCircle', [8728]], ['smallsetminus', [8726]], ['smashp', [10803]], ['smeparsl', [10724]], ['smid', [8739]], ['smile', [8995]], ['smt', [10922]], ['smte', [10924]], ['smtes', [10924, 65024]], ['SOFTcy', [1068]], ['softcy', [1100]], ['solbar', [9023]], ['solb', [10692]], ['sol', [47]], ['Sopf', [120138]], ['sopf', [120164]], ['spades', [9824]], ['spadesuit', [9824]], ['spar', [8741]], ['sqcap', [8851]], ['sqcaps', [8851, 65024]], ['sqcup', [8852]], ['sqcups', [8852, 65024]], ['Sqrt', [8730]], ['sqsub', [8847]], ['sqsube', [8849]], ['sqsubset', [8847]], ['sqsubseteq', [8849]], ['sqsup', [8848]], ['sqsupe', [8850]], ['sqsupset', [8848]], ['sqsupseteq', [8850]], ['square', [9633]], ['Square', [9633]], ['SquareIntersection', [8851]], ['SquareSubset', [8847]], ['SquareSubsetEqual', [8849]], ['SquareSuperset', [8848]], ['SquareSupersetEqual', [8850]], ['SquareUnion', [8852]], ['squarf', [9642]], ['squ', [9633]], ['squf', [9642]], ['srarr', [8594]], ['Sscr', [119982]], ['sscr', [120008]], ['ssetmn', [8726]], ['ssmile', [8995]], ['sstarf', [8902]], ['Star', [8902]], ['star', [9734]], ['starf', [9733]], ['straightepsilon', [1013]], ['straightphi', [981]], ['strns', [175]], ['sub', [8834]], ['Sub', [8912]], ['subdot', [10941]], ['subE', [10949]], ['sube', [8838]], ['subedot', [10947]], ['submult', [10945]], ['subnE', [10955]], ['subne', [8842]], ['subplus', [10943]], ['subrarr', [10617]], ['subset', [8834]], ['Subset', [8912]], ['subseteq', [8838]], ['subseteqq', [10949]], ['SubsetEqual', [8838]], ['subsetneq', [8842]], ['subsetneqq', [10955]], ['subsim', [10951]], ['subsub', [10965]], ['subsup', [10963]], ['succapprox', [10936]], ['succ', [8827]], ['succcurlyeq', [8829]], ['Succeeds', [8827]], ['SucceedsEqual', [10928]], ['SucceedsSlantEqual', [8829]], ['SucceedsTilde', [8831]], ['succeq', [10928]], ['succnapprox', [10938]], ['succneqq', [10934]], ['succnsim', [8937]], ['succsim', [8831]], ['SuchThat', [8715]], ['sum', [8721]], ['Sum', [8721]], ['sung', [9834]], ['sup1', [185]], ['sup2', [178]], ['sup3', [179]], ['sup', [8835]], ['Sup', [8913]], ['supdot', [10942]], ['supdsub', [10968]], ['supE', [10950]], ['supe', [8839]], ['supedot', [10948]], ['Superset', [8835]], ['SupersetEqual', [8839]], ['suphsol', [10185]], ['suphsub', [10967]], ['suplarr', [10619]], ['supmult', [10946]], ['supnE', [10956]], ['supne', [8843]], ['supplus', [10944]], ['supset', [8835]], ['Supset', [8913]], ['supseteq', [8839]], ['supseteqq', [10950]], ['supsetneq', [8843]], ['supsetneqq', [10956]], ['supsim', [10952]], ['supsub', [10964]], ['supsup', [10966]], ['swarhk', [10534]], ['swarr', [8601]], ['swArr', [8665]], ['swarrow', [8601]], ['swnwar', [10538]], ['szlig', [223]], ['Tab', [9]], ['target', [8982]], ['Tau', [932]], ['tau', [964]], ['tbrk', [9140]], ['Tcaron', [356]], ['tcaron', [357]], ['Tcedil', [354]], ['tcedil', [355]], ['Tcy', [1058]], ['tcy', [1090]], ['tdot', [8411]], ['telrec', [8981]], ['Tfr', [120087]], ['tfr', [120113]], ['there4', [8756]], ['therefore', [8756]], ['Therefore', [8756]], ['Theta', [920]], ['theta', [952]], ['thetasym', [977]], ['thetav', [977]], ['thickapprox', [8776]], ['thicksim', [8764]], ['ThickSpace', [8287, 8202]], ['ThinSpace', [8201]], ['thinsp', [8201]], ['thkap', [8776]], ['thksim', [8764]], ['THORN', [222]], ['thorn', [254]], ['tilde', [732]], ['Tilde', [8764]], ['TildeEqual', [8771]], ['TildeFullEqual', [8773]], ['TildeTilde', [8776]], ['timesbar', [10801]], ['timesb', [8864]], ['times', [215]], ['timesd', [10800]], ['tint', [8749]], ['toea', [10536]], ['topbot', [9014]], ['topcir', [10993]], ['top', [8868]], ['Topf', [120139]], ['topf', [120165]], ['topfork', [10970]], ['tosa', [10537]], ['tprime', [8244]], ['trade', [8482]], ['TRADE', [8482]], ['triangle', [9653]], ['triangledown', [9663]], ['triangleleft', [9667]], ['trianglelefteq', [8884]], ['triangleq', [8796]], ['triangleright', [9657]], ['trianglerighteq', [8885]], ['tridot', [9708]], ['trie', [8796]], ['triminus', [10810]], ['TripleDot', [8411]], ['triplus', [10809]], ['trisb', [10701]], ['tritime', [10811]], ['trpezium', [9186]], ['Tscr', [119983]], ['tscr', [120009]], ['TScy', [1062]], ['tscy', [1094]], ['TSHcy', [1035]], ['tshcy', [1115]], ['Tstrok', [358]], ['tstrok', [359]], ['twixt', [8812]], ['twoheadleftarrow', [8606]], ['twoheadrightarrow', [8608]], ['Uacute', [218]], ['uacute', [250]], ['uarr', [8593]], ['Uarr', [8607]], ['uArr', [8657]], ['Uarrocir', [10569]], ['Ubrcy', [1038]], ['ubrcy', [1118]], ['Ubreve', [364]], ['ubreve', [365]], ['Ucirc', [219]], ['ucirc', [251]], ['Ucy', [1059]], ['ucy', [1091]], ['udarr', [8645]], ['Udblac', [368]], ['udblac', [369]], ['udhar', [10606]], ['ufisht', [10622]], ['Ufr', [120088]], ['ufr', [120114]], ['Ugrave', [217]], ['ugrave', [249]], ['uHar', [10595]], ['uharl', [8639]], ['uharr', [8638]], ['uhblk', [9600]], ['ulcorn', [8988]], ['ulcorner', [8988]], ['ulcrop', [8975]], ['ultri', [9720]], ['Umacr', [362]], ['umacr', [363]], ['uml', [168]], ['UnderBar', [95]], ['UnderBrace', [9183]], ['UnderBracket', [9141]], ['UnderParenthesis', [9181]], ['Union', [8899]], ['UnionPlus', [8846]], ['Uogon', [370]], ['uogon', [371]], ['Uopf', [120140]], ['uopf', [120166]], ['UpArrowBar', [10514]], ['uparrow', [8593]], ['UpArrow', [8593]], ['Uparrow', [8657]], ['UpArrowDownArrow', [8645]], ['updownarrow', [8597]], ['UpDownArrow', [8597]], ['Updownarrow', [8661]], ['UpEquilibrium', [10606]], ['upharpoonleft', [8639]], ['upharpoonright', [8638]], ['uplus', [8846]], ['UpperLeftArrow', [8598]], ['UpperRightArrow', [8599]], ['upsi', [965]], ['Upsi', [978]], ['upsih', [978]], ['Upsilon', [933]], ['upsilon', [965]], ['UpTeeArrow', [8613]], ['UpTee', [8869]], ['upuparrows', [8648]], ['urcorn', [8989]], ['urcorner', [8989]], ['urcrop', [8974]], ['Uring', [366]], ['uring', [367]], ['urtri', [9721]], ['Uscr', [119984]], ['uscr', [120010]], ['utdot', [8944]], ['Utilde', [360]], ['utilde', [361]], ['utri', [9653]], ['utrif', [9652]], ['uuarr', [8648]], ['Uuml', [220]], ['uuml', [252]], ['uwangle', [10663]], ['vangrt', [10652]], ['varepsilon', [1013]], ['varkappa', [1008]], ['varnothing', [8709]], ['varphi', [981]], ['varpi', [982]], ['varpropto', [8733]], ['varr', [8597]], ['vArr', [8661]], ['varrho', [1009]], ['varsigma', [962]], ['varsubsetneq', [8842, 65024]], ['varsubsetneqq', [10955, 65024]], ['varsupsetneq', [8843, 65024]], ['varsupsetneqq', [10956, 65024]], ['vartheta', [977]], ['vartriangleleft', [8882]], ['vartriangleright', [8883]], ['vBar', [10984]], ['Vbar', [10987]], ['vBarv', [10985]], ['Vcy', [1042]], ['vcy', [1074]], ['vdash', [8866]], ['vDash', [8872]], ['Vdash', [8873]], ['VDash', [8875]], ['Vdashl', [10982]], ['veebar', [8891]], ['vee', [8744]], ['Vee', [8897]], ['veeeq', [8794]], ['vellip', [8942]], ['verbar', [124]], ['Verbar', [8214]], ['vert', [124]], ['Vert', [8214]], ['VerticalBar', [8739]], ['VerticalLine', [124]], ['VerticalSeparator', [10072]], ['VerticalTilde', [8768]], ['VeryThinSpace', [8202]], ['Vfr', [120089]], ['vfr', [120115]], ['vltri', [8882]], ['vnsub', [8834, 8402]], ['vnsup', [8835, 8402]], ['Vopf', [120141]], ['vopf', [120167]], ['vprop', [8733]], ['vrtri', [8883]], ['Vscr', [119985]], ['vscr', [120011]], ['vsubnE', [10955, 65024]], ['vsubne', [8842, 65024]], ['vsupnE', [10956, 65024]], ['vsupne', [8843, 65024]], ['Vvdash', [8874]], ['vzigzag', [10650]], ['Wcirc', [372]], ['wcirc', [373]], ['wedbar', [10847]], ['wedge', [8743]], ['Wedge', [8896]], ['wedgeq', [8793]], ['weierp', [8472]], ['Wfr', [120090]], ['wfr', [120116]], ['Wopf', [120142]], ['wopf', [120168]], ['wp', [8472]], ['wr', [8768]], ['wreath', [8768]], ['Wscr', [119986]], ['wscr', [120012]], ['xcap', [8898]], ['xcirc', [9711]], ['xcup', [8899]], ['xdtri', [9661]], ['Xfr', [120091]], ['xfr', [120117]], ['xharr', [10231]], ['xhArr', [10234]], ['Xi', [926]], ['xi', [958]], ['xlarr', [10229]], ['xlArr', [10232]], ['xmap', [10236]], ['xnis', [8955]], ['xodot', [10752]], ['Xopf', [120143]], ['xopf', [120169]], ['xoplus', [10753]], ['xotime', [10754]], ['xrarr', [10230]], ['xrArr', [10233]], ['Xscr', [119987]], ['xscr', [120013]], ['xsqcup', [10758]], ['xuplus', [10756]], ['xutri', [9651]], ['xvee', [8897]], ['xwedge', [8896]], ['Yacute', [221]], ['yacute', [253]], ['YAcy', [1071]], ['yacy', [1103]], ['Ycirc', [374]], ['ycirc', [375]], ['Ycy', [1067]], ['ycy', [1099]], ['yen', [165]], ['Yfr', [120092]], ['yfr', [120118]], ['YIcy', [1031]], ['yicy', [1111]], ['Yopf', [120144]], ['yopf', [120170]], ['Yscr', [119988]], ['yscr', [120014]], ['YUcy', [1070]], ['yucy', [1102]], ['yuml', [255]], ['Yuml', [376]], ['Zacute', [377]], ['zacute', [378]], ['Zcaron', [381]], ['zcaron', [382]], ['Zcy', [1047]], ['zcy', [1079]], ['Zdot', [379]], ['zdot', [380]], ['zeetrf', [8488]], ['ZeroWidthSpace', [8203]], ['Zeta', [918]], ['zeta', [950]], ['zfr', [120119]], ['Zfr', [8488]], ['ZHcy', [1046]], ['zhcy', [1078]], ['zigrarr', [8669]], ['zopf', [120171]], ['Zopf', [8484]], ['Zscr', [119989]], ['zscr', [120015]], ['zwj', [8205]], ['zwnj', [8204]]];
var alphaIndex = {};
var charIndex = {};
createIndexes(alphaIndex, charIndex);
var Html5Entities = /** @class */ (function () {
    function Html5Entities() {
    }
    Html5Entities.prototype.decode = function (str) {
        if (!str || !str.length) {
            return '';
        }
        return str.replace(/&(#?[\w\d]+);?/g, function (s, entity) {
            var chr;
            if (entity.charAt(0) === "#") {
                var code = entity.charAt(1) === 'x' ?
                    parseInt(entity.substr(2).toLowerCase(), 16) :
                    parseInt(entity.substr(1));
                if (!(isNaN(code) || code < -32768 || code > 65535)) {
                    chr = String.fromCharCode(code);
                }
            }
            else {
                chr = alphaIndex[entity];
            }
            return chr || s;
        });
    };
    Html5Entities.decode = function (str) {
        return new Html5Entities().decode(str);
    };
    Html5Entities.prototype.encode = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var charInfo = charIndex[str.charCodeAt(i)];
            if (charInfo) {
                var alpha = charInfo[str.charCodeAt(i + 1)];
                if (alpha) {
                    i++;
                }
                else {
                    alpha = charInfo[''];
                }
                if (alpha) {
                    result += "&" + alpha + ";";
                    i++;
                    continue;
                }
            }
            result += str.charAt(i);
            i++;
        }
        return result;
    };
    Html5Entities.encode = function (str) {
        return new Html5Entities().encode(str);
    };
    Html5Entities.prototype.encodeNonUTF = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var c = str.charCodeAt(i);
            var charInfo = charIndex[c];
            if (charInfo) {
                var alpha = charInfo[str.charCodeAt(i + 1)];
                if (alpha) {
                    i++;
                }
                else {
                    alpha = charInfo[''];
                }
                if (alpha) {
                    result += "&" + alpha + ";";
                    i++;
                    continue;
                }
            }
            if (c < 32 || c > 126) {
                result += '&#' + c + ';';
            }
            else {
                result += str.charAt(i);
            }
            i++;
        }
        return result;
    };
    Html5Entities.encodeNonUTF = function (str) {
        return new Html5Entities().encodeNonUTF(str);
    };
    Html5Entities.prototype.encodeNonASCII = function (str) {
        if (!str || !str.length) {
            return '';
        }
        var strLength = str.length;
        var result = '';
        var i = 0;
        while (i < strLength) {
            var c = str.charCodeAt(i);
            if (c <= 255) {
                result += str[i++];
                continue;
            }
            result += '&#' + c + ';';
            i++;
        }
        return result;
    };
    Html5Entities.encodeNonASCII = function (str) {
        return new Html5Entities().encodeNonASCII(str);
    };
    return Html5Entities;
}());
exports.Html5Entities = Html5Entities;
function createIndexes(alphaIndex, charIndex) {
    var i = ENTITIES.length;
    while (i--) {
        var e = ENTITIES[i];
        var alpha = e[0];
        var chars = e[1];
        var chr = chars[0];
        var addChar = (chr < 32 || chr > 126) || chr === 62 || chr === 60 || chr === 38 || chr === 34 || chr === 39;
        var charInfo = void 0;
        if (addChar) {
            charInfo = charIndex[chr] = charIndex[chr] || {};
        }
        if (chars[1]) {
            var chr2 = chars[1];
            alphaIndex[alpha] = String.fromCharCode(chr) + String.fromCharCode(chr2);
            addChar && (charInfo[chr2] = alpha);
        }
        else {
            alphaIndex[alpha] = String.fromCharCode(chr);
            addChar && (charInfo[''] = alpha);
        }
    }
}


/***/ }),
/* 15 */
/*!**************************************************!*\
  !*** (webpack)-hot-middleware/process-update.js ***!
  \**************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

/**
 * Based heavily on https://github.com/webpack/webpack/blob/
 *  c0afdf9c6abc1dd70707c594e473802a566f7b6e/hot/only-dev-server.js
 * Original copyright Tobias Koppers @sokra (MIT license)
 */

/* global window __webpack_hash__ */

if (false) {
  throw new Error("[HMR] Hot Module Replacement is disabled.");
}

var hmrDocsUrl = "https://webpack.js.org/concepts/hot-module-replacement/"; // eslint-disable-line max-len

var lastHash;
var failureStatuses = { abort: 1, fail: 1 };
var applyOptions = { 				
  ignoreUnaccepted: true,
  ignoreDeclined: true,
  ignoreErrored: true,
  onUnaccepted: function(data) {
    console.warn("Ignored an update to unaccepted module " + data.chain.join(" -> "));
  },
  onDeclined: function(data) {
    console.warn("Ignored an update to declined module " + data.chain.join(" -> "));
  },
  onErrored: function(data) {
    console.error(data.error);
    console.warn("Ignored an error while updating module " + data.moduleId + " (" + data.type + ")");
  } 
}

function upToDate(hash) {
  if (hash) lastHash = hash;
  return lastHash == __webpack_require__.h();
}

module.exports = function(hash, moduleMap, options) {
  var reload = options.reload;
  if (!upToDate(hash) && module.hot.status() == "idle") {
    if (options.log) console.log("[HMR] Checking for updates on the server...");
    check();
  }

  function check() {
    var cb = function(err, updatedModules) {
      if (err) return handleError(err);

      if(!updatedModules) {
        if (options.warn) {
          console.warn("[HMR] Cannot find update (Full reload needed)");
          console.warn("[HMR] (Probably because of restarting the server)");
        }
        performReload();
        return null;
      }

      var applyCallback = function(applyErr, renewedModules) {
        if (applyErr) return handleError(applyErr);

        if (!upToDate()) check();

        logUpdates(updatedModules, renewedModules);
      };

      var applyResult = module.hot.apply(applyOptions, applyCallback);
      // webpack 2 promise
      if (applyResult && applyResult.then) {
        // HotModuleReplacement.runtime.js refers to the result as `outdatedModules`
        applyResult.then(function(outdatedModules) {
          applyCallback(null, outdatedModules);
        });
        applyResult.catch(applyCallback);
      }

    };

    var result = module.hot.check(false, cb);
    // webpack 2 promise
    if (result && result.then) {
        result.then(function(updatedModules) {
            cb(null, updatedModules);
        });
        result.catch(cb);
    }
  }

  function logUpdates(updatedModules, renewedModules) {
    var unacceptedModules = updatedModules.filter(function(moduleId) {
      return renewedModules && renewedModules.indexOf(moduleId) < 0;
    });

    if(unacceptedModules.length > 0) {
      if (options.warn) {
        console.warn(
          "[HMR] The following modules couldn't be hot updated: " +
          "(Full reload needed)\n" +
          "This is usually because the modules which have changed " +
          "(and their parents) do not know how to hot reload themselves. " +
          "See " + hmrDocsUrl + " for more details."
        );
        unacceptedModules.forEach(function(moduleId) {
          console.warn("[HMR]  - " + moduleMap[moduleId]);
        });
      }
      performReload();
      return;
    }

    if (options.log) {
      if(!renewedModules || renewedModules.length === 0) {
        console.log("[HMR] Nothing hot updated.");
      } else {
        console.log("[HMR] Updated modules:");
        renewedModules.forEach(function(moduleId) {
          console.log("[HMR]  - " + moduleMap[moduleId]);
        });
      }

      if (upToDate()) {
        console.log("[HMR] App is up to date.");
      }
    }
  }

  function handleError(err) {
    if (module.hot.status() in failureStatuses) {
      if (options.warn) {
        console.warn("[HMR] Cannot check for update (Full reload needed)");
        console.warn("[HMR] " + err.stack || err.message);
      }
      performReload();
      return;
    }
    if (options.warn) {
      console.warn("[HMR] Update check failed: " + err.stack || err.message);
    }
  }

  function performReload() {
    if (reload) {
      if (options.warn) console.warn("[HMR] Reloading page");
      window.location.reload();
    }
  }
};


/***/ }),
/* 16 */
/*!********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/cache-loader/dist/cjs.js!/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/css-loader?{"sourceMap":true}!/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/postcss-loader/lib?{"config":{"path":"/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/build","ctx":{"open":true,"copy":"images/**_/*","proxyUrl":"http://localhost:3000","cacheBusting":"[name]_[hash:8]","paths":{"root":"/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck","assets":"/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets","dist":"/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/dist"},"enabled":{"sourceMaps":true,"optimize":false,"cacheBusting":false,"watcher":true},"watch":["app/**_/*.php","config/**_/*.php","resources/views/**_/*.php","resources/views/**_/*.twig"],"entry":{"main":["./scripts/main.js","./styles/main.scss"],"customizer":["./scripts/customizer.js"]},"publicPath":"/wp-content/themes/angry-duck/dist/","devUrl":"http://angry-duck.test","env":{"production":false,"development":true},"manifest":{}}},"sourceMap":true}!/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/resolve-url-loader?{"sourceMap":true}!/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/sass-loader/lib/loader.js?{"sourceMap":true}!/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/import-glob!./styles/main.scss ***!
  \********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

var escape = __webpack_require__(/*! ../../../node_modules/css-loader/lib/url/escape.js */ 23);
exports = module.exports = __webpack_require__(/*! ../../../node_modules/css-loader/lib/css-base.js */ 24)(true);
// imports
exports.push([module.i, "@import url(https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;\n\n0,700;1,400;1,700&display=swap);", ""]);

// module
exports.push([module.i, "@charset \"UTF-8\";\n\n/**\n * CONTENTS\n *\n * SETTINGS\n * Variables............Globally-available variables and config.\n * Z-Index..............Semantic z-index manifest\n *\n * TOOLS\n * Mixins...............Useful mixins.\n * Include Media........Sass library for writing CSS media queries.\n * Media Query Test.....Displays the current breakport you're in.\n *\n * GENERIC\n * Reset................A level playing field.\n *\n * BASE\n * Forms................Common and default form styles.\n * Headings.............H1–H6 styles.\n * Links................Link styles.\n * Lists................Default list styles.\n * Main.................Page body defaults.\n * Media................Image and video styles.\n * Tables...............Default table styles.\n * Text.................Default text styles.\n *\n * LAYOUT\n * Grids................Grid/column classes.\n * Wrappers.............Wrapping/constraining elements.\n *\n * COMPONENTS\n * Blocks...............Modular components often consisting of text and media.\n * Cards................Modular components for mainly text and data (card-like).\n * Heros................Leading hero image/caption section of a content type.\n * Sections.............Larger components of pages.\n * Forms................Specific form styling.\n *\n * OBJECTS\n * Buttons..............Various button styles and styles.\n * Icons................Icon styles and settings.\n * Lists................Various site list styles.\n * Navs.................Site navigations.\n * Media................Specific media objects, e.g. figures\n *\n * TEXT\n * Text.................Various text-specific class definitions.\n *\n * PAGE STRUCTURE\n * Article..............Post-type pages with styled text.\n * Gallery..............Styles for all things gallery.\n * Footer...............The main page footer.\n * Header...............The main page header.\n * Main.................Content area styles.\n *\n * MODIFIERS\n * Animations...........Animation and transition effects.\n * Colors...............Text and background colors.\n * Display..............Show and hide and breakpoint visibility rules.\n * Spacings.............Padding and margins in classes.\n *\n * TRUMPS\n * Helper Classes.......Helper classes loaded last in the cascade.\n */\n\n/* ------------------------------------ *\\\n    $SETTINGS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $VARIABLES\n\\* ------------------------------------ */\n\n/**\n * Grid & Baseline Setup\n */\n\n/**\n * Theme Colors\n */\n\n/**\n * Default Colors\n */\n\n/**\n * Style Colors\n */\n\n/**\n * Typography\n */\n\n/**\n * Icons\n */\n\n/**\n * Common Breakpoints\n */\n\n/**\n * Animation\n */\n\n/**\n * Border Styles\n */\n\n/**\n * Default Spacing/Padding\n * Maintain a spacing system divisible by 10\n */\n\n/**\n * Font Sizes\n */\n\n/**\n * Native Custom Properties\n */\n:root {\n  --body-font-size: 16px;\n  --font-size-xs: 13px;\n  --font-size-s: 16px;\n  --font-size-m: 20px;\n  --font-size-l: 22px;\n  --font-size-xl: 28px;\n  --font-size-xxl: 36px;\n}\n\n@media screen and (min-width: 700px) {\n  :root {\n    --font-size-xs: 14px;\n    --font-size-s: 18px;\n    --font-size-m: 22px;\n    --font-size-l: 24px;\n    --font-size-xl: 32px;\n    --font-size-xxl: 40px;\n  }\n}\n\n/* ------------------------------------ *\\\n    $TOOLS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $MIXINS\n\\* ------------------------------------ */\n\n/**\n * Standard paragraph\n */\n\n/**\n * String interpolation function for SASS variables in SVG Image URI's\n */\n\n/* ------------------------------------ *\\\n    $MEDIA QUERY TESTS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $GENERIC\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $RESET\n\\* ------------------------------------ */\n\n/* Border-Box http:/paulirish.com/2012/box-sizing-border-box-ftw/ */\n\n*,\n*::before,\n*::after {\n  -webkit-box-sizing: border-box;\n          box-sizing: border-box;\n}\n\nbody {\n  margin: 0;\n  padding: 0;\n}\n\nblockquote,\nbody,\ndiv,\nfigure,\nfooter,\nform,\nh1,\nh2,\nh3,\nh4,\nh5,\nh6,\nheader,\nhtml,\niframe,\nlabel,\nlegend,\nli,\nnav,\nobject,\nol,\np,\nsection,\ntable,\nul {\n  margin: 0;\n  padding: 0;\n}\n\narticle,\nfigure,\nfooter,\nheader,\nhgroup,\nnav,\nsection {\n  display: block;\n}\n\naddress {\n  font-style: normal;\n}\n\n/* ------------------------------------ *\\\n    $BASE\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $FONTS\n\\* ------------------------------------ */\n\n@font-face {\n  font-family: 'Big John';\n  src: url(" + escape(__webpack_require__(/*! ../fonts/big_john-webfont.woff2 */ 25)) + ") format(\"woff2\"), url(" + escape(__webpack_require__(/*! ../fonts/big_john-webfont.woff */ 26)) + ") format(\"woff\");\n  font-weight: normal;\n  font-style: normal;\n}\n\n@font-face {\n  font-family: 'Go Bold';\n  src: url(" + escape(__webpack_require__(/*! ../fonts/gobold_regular_italic-webfont.woff2 */ 27)) + ") format(\"woff2\"), url(" + escape(__webpack_require__(/*! ../fonts/gobold_regular_italic-webfont.woff */ 28)) + ") format(\"woff\");\n  font-weight: normal;\n  font-style: normal;\n}\n\n/* ------------------------------------ *\\\n    $FORMS\n\\* ------------------------------------ */\n\nform ol,\nform ul {\n  list-style: none;\n  margin-left: 0;\n}\n\nlegend {\n  margin-bottom: 6px;\n  font-weight: bold;\n}\n\nfieldset {\n  border: 0;\n  padding: 0;\n  margin: 0;\n  min-width: 0;\n}\n\ninput,\nselect,\ntextarea {\n  width: 100%;\n  border: none;\n  -webkit-appearance: none;\n     -moz-appearance: none;\n          appearance: none;\n}\n\ninput[type=text],\ninput[type=password],\ninput[type=email],\ninput[type=search],\ninput[type=tel],\nselect {\n  font-size: 16px;\n  font-family: \"Roboto\", sans-serif;\n  padding: 18px 20px;\n  -webkit-box-shadow: none;\n          box-shadow: none;\n  border: 1px solid #666;\n}\n\ninput[type=text]::-webkit-input-placeholder,\ninput[type=password]::-webkit-input-placeholder,\ninput[type=email]::-webkit-input-placeholder,\ninput[type=search]::-webkit-input-placeholder,\ninput[type=tel]::-webkit-input-placeholder,\nselect::-webkit-input-placeholder {\n  color: #666;\n}\n\ninput[type=text]::-moz-placeholder,\ninput[type=password]::-moz-placeholder,\ninput[type=email]::-moz-placeholder,\ninput[type=search]::-moz-placeholder,\ninput[type=tel]::-moz-placeholder,\nselect::-moz-placeholder {\n  color: #666;\n}\n\ninput[type=text]::-ms-input-placeholder,\ninput[type=password]::-ms-input-placeholder,\ninput[type=email]::-ms-input-placeholder,\ninput[type=search]::-ms-input-placeholder,\ninput[type=tel]::-ms-input-placeholder,\nselect::-ms-input-placeholder {\n  color: #666;\n}\n\ninput[type=text]::placeholder,\ninput[type=password]::placeholder,\ninput[type=email]::placeholder,\ninput[type=search]::placeholder,\ninput[type=tel]::placeholder,\nselect::placeholder {\n  color: #666;\n}\n\ninput[type=text]:focus,\ninput[type=password]:focus,\ninput[type=email]:focus,\ninput[type=search]:focus,\ninput[type=tel]:focus,\nselect:focus {\n  outline: 2px solid #666;\n}\n\ninput[type=radio],\ninput[type=checkbox] {\n  outline: none;\n  margin: 0;\n  margin-right: 5px;\n  height: 18px;\n  width: 18px;\n  line-height: 1;\n  background-size: 18px;\n  background-repeat: no-repeat;\n  background-position: 0 0;\n  cursor: pointer;\n  display: block;\n  float: left;\n  border: 1px solid #666;\n  padding: 0;\n  -webkit-user-select: none;\n     -moz-user-select: none;\n      -ms-user-select: none;\n          user-select: none;\n  -webkit-appearance: none;\n     -moz-appearance: none;\n          appearance: none;\n  background-color: #fff;\n}\n\ninput[type=radio] + label,\ninput[type=checkbox] + label {\n  display: inline-block;\n  cursor: pointer;\n  position: relative;\n  margin-bottom: 0;\n}\n\ninput[type=radio]:checked,\ninput[type=checkbox]:checked {\n  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3E%3Cpath d='M26.08,3.56l-2,1.95L10.61,19l-5-4L3.47,13.29,0,17.62l2.17,1.73L9.1,24.9,11,26.44l1.77-1.76L28.05,9.43,30,7.48Z' fill='%23ef4438'/%3E%3C/svg%3E\");\n  background-repeat: no-repeat;\n  background-position: center center;\n  background-size: 10px;\n}\n\ninput[type=radio] {\n  border-radius: 50px;\n}\n\ninput[type=checkbox] {\n  border-radius: 2px;\n}\n\ninput[type=submit] {\n  -webkit-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  -o-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n}\n\n/* clears the 'X' from Internet Explorer */\n\ninput[type=search]::-ms-clear {\n  display: none;\n  width: 0;\n  height: 0;\n}\n\ninput[type=search]::-ms-reveal {\n  display: none;\n  width: 0;\n  height: 0;\n}\n\n/* clears the 'X' from Chrome */\n\ninput[type=\"search\"]::-webkit-search-decoration,\ninput[type=\"search\"]::-webkit-search-cancel-button,\ninput[type=\"search\"]::-webkit-search-results-button,\ninput[type=\"search\"]::-webkit-search-results-decoration {\n  display: none;\n}\n\n/* removes the blue background on Chrome's autocomplete */\n\ninput:-webkit-autofill,\ninput:-webkit-autofill:hover,\ninput:-webkit-autofill:focus,\ninput:-webkit-autofill:active {\n  -webkit-box-shadow: 0 0 0 30px white inset;\n}\n\nselect {\n  background-color: #fff;\n  -webkit-appearance: none;\n     -moz-appearance: none;\n          appearance: none;\n  position: relative;\n  width: 100%;\n  padding-right: 30px;\n}\n\n/* ------------------------------------ *\\\n    $HEADINGS\n\\* ------------------------------------ */\n\nh1,\n.o-heading--xxl {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-xxl, 40px);\n  line-height: 1.3;\n}\n\nh2,\n.o-heading--xl {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-xl, 32px);\n  line-height: 1.3;\n}\n\nh3,\n.o-heading--l {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-l, 24px);\n  line-height: 1.4;\n}\n\nh4,\n.o-heading--m {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-m, 22px);\n  line-height: 1.4;\n}\n\nh5,\n.o-heading--s {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-s, 18px);\n  line-height: 1.6;\n}\n\nh6,\n.o-heading--xs {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-xs, 14px);\n  line-height: 1.5;\n}\n\n/* ------------------------------------ *\\\n    $LINKS\n\\* ------------------------------------ */\n\na {\n  text-decoration: none;\n  color: #ef4438;\n  -webkit-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  -o-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n}\n\na:hover {\n  color: darker(#ef4438, 10%);\n}\n\n/* ------------------------------------ *\\\n    $LISTS\n\\* ------------------------------------ */\n\nol,\nul {\n  margin: 0;\n  padding: 0;\n  list-style: none;\n}\n\n/**\n * Definition Lists\n */\n\ndl {\n  overflow: hidden;\n  margin: 0 0 20px;\n}\n\ndt {\n  font-weight: bold;\n}\n\ndd {\n  margin-left: 0;\n}\n\n/* ------------------------------------ *\\\n    $SITE MAIN\n\\* ------------------------------------ */\n\nbody {\n  background: #fff;\n  font: 400 16px/1.3 \"Roboto\", sans-serif;\n  -webkit-text-size-adjust: 100%;\n  color: #000;\n  -webkit-font-smoothing: antialiased;\n  -moz-osx-font-smoothing: grayscale;\n}\n\n/* ------------------------------------ *\\\n    $MEDIA ELEMENTS\n\\* ------------------------------------ */\n\n/**\n * Flexible Media\n */\n\nimg,\nvideo,\nobject,\nsvg,\niframe {\n  max-width: 100%;\n  border: none;\n  display: block;\n}\n\nimg {\n  height: auto;\n}\n\nsvg {\n  max-height: 100%;\n}\n\npicture,\npicture img {\n  display: block;\n}\n\nfigure {\n  position: relative;\n  display: inline-block;\n  overflow: hidden;\n}\n\nfigcaption a {\n  display: block;\n}\n\n/* ------------------------------------ *\\\n    $PRINT STYLES\n\\* ------------------------------------ */\n\n@media print {\n  *,\n  *::before,\n  *::after,\n  *::first-letter,\n  *::first-line {\n    background: transparent !important;\n    color: black !important;\n    -webkit-box-shadow: none !important;\n            box-shadow: none !important;\n    text-shadow: none !important;\n  }\n\n  a,\n  a:visited {\n    text-decoration: underline;\n  }\n\n  a[href]::after {\n    content: \" (\" attr(href) \")\";\n  }\n\n  abbr[title]::after {\n    content: \" (\" attr(title) \")\";\n  }\n\n  /*\n   * Don't show links that are fragment identifiers,\n   * or use the `javascript:` pseudo protocol\n   */\n\n  a[href^=\"#\"]::after,\n  a[href^=\"javascript:\"]::after {\n    content: \"\";\n  }\n\n  pre,\n  blockquote {\n    border: 1px solid #999;\n    page-break-inside: avoid;\n  }\n\n  /*\n   * Printing Tables:\n   * http://css-discuss.incutio.com/wiki/Printing_Tables\n   */\n\n  thead {\n    display: table-header-group;\n  }\n\n  tr,\n  img {\n    page-break-inside: avoid;\n  }\n\n  img {\n    max-width: 100% !important;\n    height: auto;\n  }\n\n  p,\n  h2,\n  h3 {\n    orphans: 3;\n    widows: 3;\n  }\n\n  h2,\n  h3 {\n    page-break-after: avoid;\n  }\n\n  .no-print,\n  .c-main-header,\n  .c-main-footer,\n  .ad {\n    display: none;\n  }\n}\n\n/* ------------------------------------ *\\\n    $TABLES\n\\* ------------------------------------ */\n\ntable {\n  border-collapse: collapse;\n  border-spacing: 0;\n  border: 1px solid #666;\n  width: 100%;\n}\n\nth {\n  text-align: left;\n  border: 1px solid transparent;\n  padding: 10px 0;\n  text-transform: uppercase;\n  vertical-align: top;\n  font-weight: bold;\n}\n\ntr {\n  border: 1px solid transparent;\n}\n\ntd {\n  border: 1px solid transparent;\n  padding: 10px;\n}\n\n/**\n * Responsive Table\n */\n\n.c-table--responsive {\n  border: 1px solid #666;\n  border-collapse: collapse;\n  padding: 0;\n  width: 100%;\n}\n\n.c-table--responsive tr {\n  border: 1px solid #666;\n  background-color: #f0f0f0;\n}\n\n.c-table--responsive th,\n.c-table--responsive td {\n  padding: 10px;\n}\n\n.c-table--responsive th {\n  font-size: var(--font-size-xs, 14px);\n  text-transform: uppercase;\n  border-bottom: 1px solid #666;\n}\n\n@media (max-width: 700px) {\n  .c-table--responsive {\n    border: 0;\n  }\n\n  .c-table--responsive thead {\n    border: none;\n    clip: rect(0 0 0 0);\n    height: 1px;\n    margin: -1px;\n    overflow: hidden;\n    padding: 0;\n    position: absolute;\n    width: 1px;\n  }\n\n  .c-table--responsive tr {\n    border-bottom: 3px solid #666;\n    display: block;\n    margin-bottom: 10px;\n  }\n\n  .c-table--responsive tr.this-is-active td:not(:first-child) {\n    display: -webkit-box;\n    display: -ms-flexbox;\n    display: flex;\n  }\n\n  .c-table--responsive tr.this-is-active td:first-child::before {\n    content: \"- \" attr(data-label);\n  }\n\n  .c-table--responsive td {\n    border-bottom: 1px solid #666;\n    display: -webkit-box;\n    display: -ms-flexbox;\n    display: flex;\n    -webkit-box-align: center;\n        -ms-flex-align: center;\n            align-items: center;\n    -webkit-box-pack: justify;\n        -ms-flex-pack: justify;\n            justify-content: space-between;\n    min-height: 40px;\n  }\n\n  .c-table--responsive td:first-child {\n    cursor: pointer;\n  }\n\n  .c-table--responsive td:first-child::before {\n    content: \"+ \" attr(data-label);\n  }\n\n  .c-table--responsive td:last-child {\n    border-bottom: 0;\n  }\n\n  .c-table--responsive td:not(:first-child) {\n    display: none;\n  }\n\n  .c-table--responsive td::before {\n    content: attr(data-label);\n    font-weight: bold;\n    text-transform: uppercase;\n    font-size: var(--font-size-xs, 14px);\n  }\n}\n\n/* ------------------------------------ *\\\n    $TEXT ELEMENTS\n\\* ------------------------------------ */\n\n/**\n * Text-Related Elements\n */\n\np {\n  line-height: 1.5;\n  font-family: \"Roboto\", sans-serif;\n  font-size: var(--body-font-size, 16px);\n}\n\n@media print {\n  p {\n    font-size: 12px;\n    line-height: 1.3;\n  }\n}\n\nsmall {\n  font-size: 90%;\n}\n\n/**\n * Bold\n */\n\nstrong,\nb {\n  font-weight: bold;\n}\n\n/**\n * Blockquote\n */\n\nblockquote {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n}\n\nblockquote::before {\n  content: \"\\201C\";\n  font-family: \"Roboto\", sans-serif;\n  font-size: 40px;\n  line-height: 1;\n  color: #ef4438;\n  min-width: 40px;\n  border-right: 6px solid #666;\n  display: block;\n  margin-right: 20px;\n}\n\nblockquote p {\n  line-height: 1.7;\n  -webkit-box-flex: 1;\n      -ms-flex: 1;\n          flex: 1;\n}\n\n/**\n * Horizontal Rule\n */\n\nhr {\n  height: 1px;\n  border: none;\n  background-color: rgba(240, 240, 240, 0.5);\n  margin: 0 auto;\n}\n\n/**\n * Abbreviation\n */\n\nabbr {\n  border-bottom: 1px dotted #666;\n  cursor: help;\n}\n\n/* ------------------------------------ *\\\n    $LAYOUT\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $GRIDS\n\\* ------------------------------------ */\n\n.l-grid {\n  display: grid;\n  grid-template-rows: auto;\n  grid-column-gap: 20px;\n  grid-row-gap: 20px;\n}\n\n@media all and (-ms-high-contrast: none) {\n  .l-grid {\n    display: -webkit-box;\n    display: -ms-flexbox;\n    display: flex;\n    -webkit-box-orient: horizontal;\n    -webkit-box-direction: normal;\n        -ms-flex-direction: row;\n            flex-direction: row;\n    -ms-flex-wrap: wrap;\n        flex-wrap: wrap;\n    margin-left: -20px;\n    margin-right: -20px;\n  }\n\n  .l-grid > * {\n    margin: 20px;\n  }\n}\n\n.l-grid-item {\n  position: relative;\n}\n\n.l-grid--large-gutters {\n  grid-column-gap: 80px;\n  grid-row-gap: 80px;\n}\n\n@media (min-width: 551px) {\n  .l-grid--2up {\n    grid-template-columns: repeat(2, 1fr);\n  }\n}\n\n@media all and (-ms-high-contrast: none) {\n  .l-grid--2up > * {\n    width: calc(50% - 40px);\n  }\n}\n\n.l-grid--2up--flex {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -ms-flex-wrap: wrap;\n      flex-wrap: wrap;\n  margin: 0 calc(20px * -1);\n}\n\n.l-grid--2up--flex > * {\n  width: 100%;\n  padding-left: 20px;\n  padding-right: 20px;\n  margin-top: 40px;\n}\n\n@media (min-width: 551px) {\n  .l-grid--2up--flex > * {\n    width: 50%;\n  }\n}\n\n@media (min-width: 551px) {\n  .l-grid--3up {\n    grid-template-columns: repeat(2, 1fr);\n  }\n}\n\n@media (min-width: 851px) {\n  .l-grid--3up {\n    grid-template-columns: repeat(3, 1fr);\n  }\n}\n\n@media all and (-ms-high-contrast: none) {\n  .l-grid--3up > * {\n    width: calc(33.333% - 40px);\n  }\n}\n\n.l-grid--4up {\n  grid-template-columns: repeat(minmax(200px, 1fr));\n}\n\n@media (min-width: 401px) {\n  .l-grid--4up {\n    grid-template-columns: repeat(2, 1fr);\n  }\n}\n\n@media (min-width: 701px) {\n  .l-grid--4up {\n    grid-template-columns: repeat(3, 1fr);\n  }\n}\n\n@media (min-width: 1001px) {\n  .l-grid--4up {\n    grid-template-columns: repeat(4, 1fr);\n  }\n}\n\n@media all and (-ms-high-contrast: none) {\n  .l-grid--4up > * {\n    width: calc(25% - 40px);\n  }\n}\n\n.l-grid--4up--at-medium {\n  grid-template-columns: repeat(2, 1fr);\n}\n\n@media (min-width: 551px) {\n  .l-grid--4up--at-medium {\n    grid-template-columns: repeat(3, 1fr);\n  }\n}\n\n@media (min-width: 701px) {\n  .l-grid--4up--at-medium {\n    grid-template-columns: repeat(4, 1fr);\n  }\n}\n\n@media all and (-ms-high-contrast: none) {\n  .l-grid--4up--at-medium > * {\n    width: calc(25% - 40px);\n  }\n}\n\n.l-grid--5up {\n  grid-row-gap: 40px;\n  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));\n}\n\n@media (min-width: 851px) {\n  .l-grid--5up {\n    grid-template-columns: repeat(5, 1fr);\n  }\n}\n\n@media all and (-ms-high-contrast: none) {\n  .l-grid--5up > * {\n    width: calc(20% - 40px);\n  }\n}\n\n/* ------------------------------------ *\\\n    $WRAPPERS & CONTAINERS\n\\* ------------------------------------ */\n\n/**\n * Wrapping element to keep content contained and centered.\n */\n\n.l-wrap {\n  margin: 0 auto;\n  padding-left: 20px;\n  padding-right: 20px;\n  width: 100%;\n  position: relative;\n}\n\n@media (min-width: 1201px) {\n  .l-wrap {\n    padding-left: 40px;\n    padding-right: 40px;\n  }\n}\n\n/**\n * Layout containers - keep content centered and within a maximum width. Also\n * adjusts left and right padding as the viewport widens.\n */\n\n.l-container {\n  max-width: 1200px;\n  margin-left: auto;\n  margin-right: auto;\n  position: relative;\n}\n\n.l-container--xl {\n  max-width: 1600px;\n}\n\n/**\n * Grid classes\n */\n\n.l-container--1col {\n  max-width: 85px;\n}\n\n.l-container--2col {\n  max-width: 150px;\n}\n\n.l-container--3col {\n  max-width: 215px;\n}\n\n.l-container--4col {\n  max-width: 300px;\n}\n\n.l-container--5col {\n  max-width: 385px;\n}\n\n.l-container--6col {\n  max-width: 470px;\n}\n\n.l-container--7col {\n  max-width: 555px;\n}\n\n.l-container--8col {\n  max-width: 640px;\n}\n\n.l-container--9col {\n  max-width: 725px;\n}\n\n.l-container--10col {\n  max-width: 810px;\n}\n\n.l-container--11col {\n  max-width: 895px;\n}\n\n.l-container--12col {\n  max-width: 980px;\n}\n\n.l-container--13col {\n  max-width: 1065px;\n}\n\n.l-container--14col {\n  max-width: 1150px;\n}\n\n.l-container--15col {\n  max-width: 1235px;\n}\n\n.l-container--16col {\n  max-width: 1320px;\n}\n\n/* ------------------------------------ *\\\n    $TEXT\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $TEXT TYPES\n\\* ------------------------------------ */\n\n/**\n * Font Families\n */\n\n.u-font {\n  font-family: \"Roboto\", sans-serif;\n}\n\n.u-font--primary,\n.u-font--primary p {\n  font-family: \"Go Bold\", sans-serif;\n}\n\n.u-font--secondary,\n.u-font--secondary p {\n  font-family: \"Big John\", sans-serif;\n}\n\n/**\n * Text Sizes\n */\n\n.u-font--xs {\n  font-size: var(--font-size-xs, 14px);\n}\n\n.u-font--s {\n  font-size: var(--font-size-s, 18px);\n}\n\n.u-font--m {\n  font-size: var(--font-size-m, 22px);\n}\n\n.u-font--l {\n  font-size: var(--font-size-l, 24px);\n}\n\n.u-font--xl {\n  font-size: var(--font-size-xl, 32px);\n}\n\n.u-font--xxl {\n  font-size: var(--font-size-xxl, 40px);\n}\n\n/**\n * Text Transforms\n */\n\n.u-text-transform--upper {\n  text-transform: uppercase;\n}\n\n.u-text-transform--lower {\n  text-transform: lowercase;\n}\n\n/**\n * Text Styles\n */\n\n.u-text-style--italic {\n  font-style: italic;\n}\n\n.u-font-weight--normal {\n  font-weight: normal;\n}\n\n/**\n * Text Positioning\n */\n\n.u-align--center {\n  text-align: center;\n}\n\n/**\n * Text Decorations\n */\n\n.u-text-decoration--underline {\n  text-decoration: underline;\n}\n\n/**\n * Rich text editor text\n */\n\n.o-rte-text {\n  width: 100%;\n  margin: 0 auto;\n}\n\n.o-rte-text > * + * {\n  margin-top: 20px;\n}\n\n.o-rte-text > dl dd,\n.o-rte-text > dl dt,\n.o-rte-text > ol li,\n.o-rte-text > ul li,\n.o-rte-text > p {\n  line-height: 1.5;\n  font-family: \"Roboto\", sans-serif;\n  font-size: var(--body-font-size, 16px);\n}\n\n@media print {\n  .o-rte-text > dl dd,\n  .o-rte-text > dl dt,\n  .o-rte-text > ol li,\n  .o-rte-text > ul li,\n  .o-rte-text > p {\n    font-size: 12px;\n    line-height: 1.3;\n  }\n}\n\n.o-rte-text > h1,\n.o-rte-text > h2,\n.o-rte-text > h3,\n.o-rte-text > h4,\n.o-rte-text > h5,\n.o-rte-text > h6 {\n  padding-top: 20px;\n  margin-bottom: -10px;\n}\n\n.o-rte-text h2:empty,\n.o-rte-text h3:empty,\n.o-rte-text p:empty {\n  display: none;\n}\n\n.o-rte-text > h2 + h3 {\n  margin-top: 0;\n  padding-top: 10px;\n}\n\n.o-rte-text a {\n  text-decoration: underline;\n}\n\n.o-rte-text hr {\n  margin-top: 40px;\n  margin-bottom: 40px;\n}\n\n.o-rte-text code,\n.o-rte-text pre {\n  font-size: 125%;\n}\n\n.o-rte-text ol,\n.o-rte-text ul {\n  padding-left: 0;\n  margin-left: 0;\n}\n\n.o-rte-text ol li,\n.o-rte-text ul li {\n  list-style: none;\n  padding-left: 34px;\n  margin-left: 0;\n  position: relative;\n  line-height: 2.1em;\n}\n\n.o-rte-text ol li::before,\n.o-rte-text ul li::before {\n  color: #ef4438;\n  width: 10px;\n  display: inline-block;\n  position: absolute;\n  left: 0;\n  font-size: 24px;\n  line-height: 1;\n  top: 4px;\n}\n\n.o-rte-text ol li li,\n.o-rte-text ul li li {\n  list-style: none;\n}\n\n.o-rte-text ol {\n  counter-reset: item;\n}\n\n.o-rte-text ol li::before {\n  content: counter(item) \". \";\n  counter-increment: item;\n}\n\n.o-rte-text ol li li {\n  counter-reset: item;\n}\n\n.o-rte-text ol li li::before {\n  content: '\\2010';\n}\n\n.o-rte-text ul li::before {\n  content: '\\2022';\n}\n\n.o-rte-text ul li li::before {\n  content: '\\25E6';\n}\n\n/* ------------------------------------ *\\\n    $BUTTONS\n\\* ------------------------------------ */\n\n/**\n * Button Primary\n */\n\n.o-button--primary {\n  display: -webkit-inline-box;\n  display: -ms-inline-flexbox;\n  display: inline-flex;\n  position: relative;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  -o-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  text-decoration: none;\n  border-radius: 0;\n  font-size: var(--body-font-size, 16px);\n  font-family: \"Go Bold\", sans-serif;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  -webkit-appearance: none;\n     -moz-appearance: none;\n          appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: 20px 40px;\n  text-transform: uppercase;\n  background-color: #ef4438;\n  color: #fff;\n  -webkit-filter: brightness(100%);\n          filter: brightness(100%);\n}\n\n.o-button--primary:hover,\n.o-button--primary:focus {\n  -webkit-filter: brightness(120%);\n          filter: brightness(120%);\n}\n\n/**\n * Button Secondary\n */\n\n.o-button--secondary {\n  display: -webkit-inline-box;\n  display: -ms-inline-flexbox;\n  display: inline-flex;\n  position: relative;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  -o-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  text-decoration: none;\n  border-radius: 0;\n  font-size: var(--body-font-size, 16px);\n  font-family: \"Go Bold\", sans-serif;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  -webkit-appearance: none;\n     -moz-appearance: none;\n          appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: 20px 40px;\n  text-transform: uppercase;\n  background-color: #ef4438;\n  color: #fff;\n  -webkit-filter: brightness(100%);\n          filter: brightness(100%);\n}\n\n.o-button--secondary:hover,\n.o-button--secondary:focus {\n  -webkit-filter: brightness(120%);\n          filter: brightness(120%);\n}\n\nbutton,\ninput[type=\"submit\"],\n.o-button {\n  display: -webkit-inline-box;\n  display: -ms-inline-flexbox;\n  display: inline-flex;\n  position: relative;\n  -webkit-box-pack: center;\n      -ms-flex-pack: center;\n          justify-content: center;\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n  -webkit-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  -o-transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  text-decoration: none;\n  border-radius: 0;\n  font-size: var(--body-font-size, 16px);\n  font-family: \"Go Bold\", sans-serif;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  -webkit-appearance: none;\n     -moz-appearance: none;\n          appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: 20px 40px;\n  text-transform: uppercase;\n  background-color: #ef4438;\n  color: #fff;\n  -webkit-filter: brightness(100%);\n          filter: brightness(100%);\n}\n\nbutton:hover,\nbutton:focus,\ninput[type=\"submit\"]:hover,\ninput[type=\"submit\"]:focus,\n.o-button:hover,\n.o-button:focus {\n  -webkit-filter: brightness(120%);\n          filter: brightness(120%);\n}\n\n/* ------------------------------------ *\\\n    $COMPONENTS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $BLOCKS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $CARDS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $HEROS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $PAGE SECTIONS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $SPECIFIC FORMS\n\\* ------------------------------------ */\n\n/**\n * Validation\n */\n\n.has-error {\n  border-color: #f00 !important;\n}\n\n.is-valid {\n  border-color: #089e00 !important;\n}\n\n/**\n * Newsletter Signup\n */\n\n.o-newsletter-signup {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-orient: vertical;\n  -webkit-box-direction: normal;\n      -ms-flex-direction: column;\n          flex-direction: column;\n}\n\n@media (min-width: 701px) {\n  .o-newsletter-signup {\n    -webkit-box-orient: horizontal;\n    -webkit-box-direction: normal;\n        -ms-flex-direction: row;\n            flex-direction: row;\n  }\n}\n\n.o-newsletter-signup input[type=email] {\n  width: 100%;\n  border: none;\n}\n\n@media (min-width: 701px) {\n  .o-newsletter-signup input[type=email] {\n    width: calc(100% - 180px);\n  }\n}\n\n.o-newsletter-signup input[type=submit] {\n  width: 100%;\n  margin-top: 10px;\n}\n\n@media (min-width: 701px) {\n  .o-newsletter-signup input[type=submit] {\n    margin-top: 0;\n    width: 180px;\n  }\n}\n\n/* ------------------------------------ *\\\n    $OBJECTS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $ICONS\n\\* ------------------------------------ */\n\n/**\n * Icon Sizing\n */\n\n.o-icon {\n  display: inline-block;\n}\n\n.u-icon--xs {\n  width: 15px;\n  height: 15px;\n}\n\n.u-icon--s {\n  width: 20px;\n  height: 20px;\n}\n\n.u-icon--m {\n  width: 30px;\n  height: 30px;\n}\n\n.u-icon--l {\n  width: 40px;\n  height: 40px;\n}\n\n.u-icon--xl {\n  width: 70px;\n  height: 70px;\n}\n\n/* ------------------------------------ *\\\n    $LIST TYPES\n\\* ------------------------------------ */\n\n/**\n * Numbered List\n */\n\n.o-list--numbered {\n  counter-reset: item;\n}\n\n.o-list--numbered li {\n  display: block;\n}\n\n.o-list--numbered li::before {\n  content: counter(item);\n  counter-increment: item;\n  color: #fff;\n  padding: 10px 15px;\n  border-radius: 3px;\n  background-color: #000;\n  font-weight: bold;\n  margin-right: 20px;\n  float: left;\n}\n\n.o-list--numbered li > * {\n  overflow: hidden;\n}\n\n.o-list--numbered li li {\n  counter-reset: item;\n}\n\n.o-list--numbered li li::before {\n  content: \"\\2010\";\n}\n\n/**\n * Bullet List\n */\n\n.o-bullet-list {\n  list-style-type: disc;\n  padding-left: 20px;\n}\n\n.o-bullet-list li {\n  overflow: visible;\n}\n\n.o-bullet-list li:last-child {\n  margin-bottom: 0;\n}\n\n/* ------------------------------------ *\\\n    $NAVIGATION\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $MEDIA OBJECTS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $PAGE STRUCTURE\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $ARTICLE & RELATED COMPONENTS\n\\* ------------------------------------ */\n\n.page-id-5 {\n  height: 100vh;\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n  -webkit-box-orient: vertical;\n  -webkit-box-direction: normal;\n      -ms-flex-direction: column;\n          flex-direction: column;\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n  background-color: #fbc617;\n}\n\n.page-id-5 .c-main-header {\n  background: url(" + escape(__webpack_require__(/*! ../images/header-bkg.png */ 29)) + ") center bottom no-repeat;\n  background-size: cover;\n}\n\n.l-article--landing {\n  padding: 0 0 40px 0;\n  max-width: 550px;\n  text-align: center;\n}\n\n/* ------------------------------------ *\\\n    $GALLERY\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $FOOTER\n\\* ------------------------------------ */\n\n.c-main-footer {\n  background-color: #ef4438;\n  color: #fff;\n}\n\n.c-main-footer--inner {\n  padding: 10px 0;\n  text-align: center;\n}\n\n/* ------------------------------------ *\\\n    $HEADER\n\\* ------------------------------------ */\n\n.o-logo {\n  margin: 40px 20px 80px 20px;\n}\n\n.o-logo img {\n  width: 100%;\n  height: auto;\n  max-width: 400px;\n  margin: 0 auto;\n  position: relative;\n}\n\n@media (min-width: 701px) {\n  .o-logo img {\n    left: -15px;\n  }\n}\n\n/* ------------------------------------ *\\\n    $MAIN CONTENT AREA\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $MODIFIERS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $ANIMATIONS & TRANSITIONS\n\\* ------------------------------------ */\n\n/**\n * Transitions\n */\n\n.has-trans {\n  -webkit-transition: all 0.4s ease-in-out;\n  -o-transition: all 0.4s ease-in-out;\n  transition: all 0.4s ease-in-out;\n}\n\n.has-trans--fast {\n  -webkit-transition: all 0.1s ease-in-out;\n  -o-transition: all 0.1s ease-in-out;\n  transition: all 0.1s ease-in-out;\n}\n\n.has-zoom {\n  overflow: hidden;\n}\n\n.has-zoom img {\n  -webkit-transition: -webkit-transform 0.3s ease-out;\n  transition: -webkit-transform 0.3s ease-out;\n  -o-transition: -o-transform 0.3s ease-out;\n  transition: transform 0.3s ease-out;\n  transition: transform 0.3s ease-out, -webkit-transform 0.3s ease-out, -o-transform 0.3s ease-out;\n  -webkit-transform: scale(1);\n       -o-transform: scale(1);\n          transform: scale(1);\n}\n\n.has-zoom a:hover img {\n  -webkit-transform: scale(1.03);\n       -o-transform: scale(1.03);\n          transform: scale(1.03);\n}\n\n/**\n * Fade Classes\n */\n\n.has-fadeup {\n  opacity: 0;\n  -webkit-transform: translate(0, 25px);\n       -o-transform: translate(0, 25px);\n          transform: translate(0, 25px);\n  -webkit-transition: all 0.6s ease-out 0.5s;\n  -o-transition: all 0.6s ease-out 0.5s;\n  transition: all 0.6s ease-out 0.5s;\n}\n\n.fadeup,\n.has-fadeup.is-active {\n  opacity: 1;\n  -webkit-transform: translate(0, 0);\n       -o-transform: translate(0, 0);\n          transform: translate(0, 0);\n}\n\n.has-fadein {\n  opacity: 0;\n  -webkit-transition: all 0.8s ease-out;\n  -o-transition: all 0.8s ease-out;\n  transition: all 0.8s ease-out;\n}\n\n.fadein {\n  opacity: 1;\n}\n\n.lazyload,\n.lazyloading {\n  opacity: 0;\n  -webkit-transform: translate(0, 25px);\n       -o-transform: translate(0, 25px);\n          transform: translate(0, 25px);\n  -webkit-transition: all 0.6s ease-out;\n  -o-transition: all 0.6s ease-out;\n  transition: all 0.6s ease-out;\n}\n\n.lazyloaded {\n  opacity: 1;\n  -webkit-transition: opacity 300ms;\n  -o-transition: opacity 300ms;\n  transition: opacity 300ms;\n}\n\n@-webkit-keyframes bounce {\n  0%, 100% {\n    -webkit-transform: translateY(0);\n            transform: translateY(0);\n  }\n\n  20% {\n    -webkit-transform: translateY(-3px);\n            transform: translateY(-3px);\n  }\n\n  80% {\n    -webkit-transform: translateY(3px);\n            transform: translateY(3px);\n  }\n}\n\n@-o-keyframes bounce {\n  0%, 100% {\n    -o-transform: translateY(0);\n       transform: translateY(0);\n  }\n\n  20% {\n    -o-transform: translateY(-3px);\n       transform: translateY(-3px);\n  }\n\n  80% {\n    -o-transform: translateY(3px);\n       transform: translateY(3px);\n  }\n}\n\n@keyframes bounce {\n  0%, 100% {\n    -webkit-transform: translateY(0);\n         -o-transform: translateY(0);\n            transform: translateY(0);\n  }\n\n  20% {\n    -webkit-transform: translateY(-3px);\n         -o-transform: translateY(-3px);\n            transform: translateY(-3px);\n  }\n\n  80% {\n    -webkit-transform: translateY(3px);\n         -o-transform: translateY(3px);\n            transform: translateY(3px);\n  }\n}\n\n/* ------------------------------------ *\\\n    $COLOR MODIFIERS\n\\* ------------------------------------ */\n\n/**\n * Text Colors\n */\n\n.u-color--black,\n.u-color--black a {\n  color: #000;\n}\n\n.u-color--gray,\n.u-color--gray a {\n  color: #666;\n}\n\n.u-color--gray--light,\n.u-color--gray--light a {\n  color: #f0f0f0;\n}\n\n.u-color--white,\n.u-color--white a {\n  color: #fff !important;\n}\n\n/**\n * Background Colors\n */\n\n.u-background-color--none {\n  background: none;\n}\n\n.u-background-color--black {\n  background-color: #000;\n}\n\n.u-background-color--gray {\n  background-color: #666;\n}\n\n.u-background-color--gray--light {\n  background-color: #f0f0f0;\n}\n\n.u-background-color--white {\n  background-color: #fff;\n}\n\n/**\n * SVG Fill Colors\n */\n\n.u-path-fill--black path {\n  fill: #000;\n}\n\n.u-path-fill--gray path {\n  fill: #666;\n}\n\n.u-path-fill--white path {\n  fill: #fff;\n}\n\n/* ------------------------------------ *\\\n    $DISPLAY STATES\n\\* ------------------------------------ */\n\n/**\n * Display Classes\n */\n\n.u-display--inline-block {\n  display: inline-block;\n}\n\n.u-display--block {\n  display: block;\n}\n\n.u-flex {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n}\n\n.u-justify-content--space-between {\n  -webkit-box-pack: justify;\n      -ms-flex-pack: justify;\n          justify-content: space-between;\n}\n\n.u-justify-content--flex-end {\n  -webkit-box-pack: end;\n      -ms-flex-pack: end;\n          justify-content: flex-end;\n}\n\n.u-align-items--center {\n  -webkit-box-align: center;\n      -ms-flex-align: center;\n          align-items: center;\n}\n\n.u-flex-directon--column {\n  -webkit-box-orient: vertical;\n  -webkit-box-direction: normal;\n      -ms-flex-direction: column;\n          flex-direction: column;\n}\n\n@media (max-width: 550px) {\n  .u-hide-until--s {\n    display: none;\n  }\n}\n\n@media (max-width: 700px) {\n  .u-hide-until--m {\n    display: none;\n  }\n}\n\n@media (max-width: 850px) {\n  .u-hide-until--l {\n    display: none;\n  }\n}\n\n@media (max-width: 1000px) {\n  .u-hide-until--xl {\n    display: none;\n  }\n}\n\n@media (min-width: 551px) {\n  .u-hide-after--s {\n    display: none;\n  }\n}\n\n@media (min-width: 701px) {\n  .u-hide-after--m {\n    display: none;\n  }\n}\n\n@media (min-width: 851px) {\n  .u-hide-after--l {\n    display: none;\n  }\n}\n\n@media (min-width: 1001px) {\n  .u-hide-after--xl {\n    display: none;\n  }\n}\n\n/* ------------------------------------ *\\\n    $SPACING\n\\* ------------------------------------ */\n\n.u-spacing > * + * {\n  margin-top: 20px;\n}\n\n.u-padding {\n  padding: 20px;\n}\n\n.u-space {\n  margin: 20px;\n}\n\n.u-padding--top {\n  padding-top: 20px;\n}\n\n.u-space--top {\n  margin-top: 20px;\n}\n\n.u-padding--bottom {\n  padding-bottom: 20px;\n}\n\n.u-space--bottom {\n  margin-bottom: 20px;\n}\n\n.u-padding--left {\n  padding-left: 20px;\n}\n\n.u-space--left {\n  margin-left: 20px;\n}\n\n.u-padding--right {\n  padding-right: 20px;\n}\n\n.u-space--right {\n  margin-right: 20px;\n}\n\n.u-spacing--quarter > * + * {\n  margin-top: 5px;\n}\n\n.u-padding--quarter {\n  padding: 5px;\n}\n\n.u-space--quarter {\n  margin: 5px;\n}\n\n.u-padding--quarter--top {\n  padding-top: 5px;\n}\n\n.u-space--quarter--top {\n  margin-top: 5px;\n}\n\n.u-padding--quarter--bottom {\n  padding-bottom: 5px;\n}\n\n.u-space--quarter--bottom {\n  margin-bottom: 5px;\n}\n\n.u-padding--quarter--left {\n  padding-left: 5px;\n}\n\n.u-space--quarter--left {\n  margin-left: 5px;\n}\n\n.u-padding--quarter--right {\n  padding-right: 5px;\n}\n\n.u-space--quarter--right {\n  margin-right: 5px;\n}\n\n.u-spacing--half > * + * {\n  margin-top: 10px;\n}\n\n.u-padding--half {\n  padding: 10px;\n}\n\n.u-space--half {\n  margin: 10px;\n}\n\n.u-padding--half--top {\n  padding-top: 10px;\n}\n\n.u-space--half--top {\n  margin-top: 10px;\n}\n\n.u-padding--half--bottom {\n  padding-bottom: 10px;\n}\n\n.u-space--half--bottom {\n  margin-bottom: 10px;\n}\n\n.u-padding--half--left {\n  padding-left: 10px;\n}\n\n.u-space--half--left {\n  margin-left: 10px;\n}\n\n.u-padding--half--right {\n  padding-right: 10px;\n}\n\n.u-space--half--right {\n  margin-right: 10px;\n}\n\n.u-spacing--and-half > * + * {\n  margin-top: 30px;\n}\n\n.u-padding--and-half {\n  padding: 30px;\n}\n\n.u-space--and-half {\n  margin: 30px;\n}\n\n.u-padding--and-half--top {\n  padding-top: 30px;\n}\n\n.u-space--and-half--top {\n  margin-top: 30px;\n}\n\n.u-padding--and-half--bottom {\n  padding-bottom: 30px;\n}\n\n.u-space--and-half--bottom {\n  margin-bottom: 30px;\n}\n\n.u-padding--and-half--left {\n  padding-left: 30px;\n}\n\n.u-space--and-half--left {\n  margin-left: 30px;\n}\n\n.u-padding--and-half--right {\n  padding-right: 30px;\n}\n\n.u-space--and-half--right {\n  margin-right: 30px;\n}\n\n.u-spacing--double > * + * {\n  margin-top: 40px;\n}\n\n.u-padding--double {\n  padding: 40px;\n}\n\n.u-space--double {\n  margin: 40px;\n}\n\n.u-padding--double--top {\n  padding-top: 40px;\n}\n\n.u-space--double--top {\n  margin-top: 40px;\n}\n\n.u-padding--double--bottom {\n  padding-bottom: 40px;\n}\n\n.u-space--double--bottom {\n  margin-bottom: 40px;\n}\n\n.u-padding--double--left {\n  padding-left: 40px;\n}\n\n.u-space--double--left {\n  margin-left: 40px;\n}\n\n.u-padding--double--right {\n  padding-right: 40px;\n}\n\n.u-space--double--right {\n  margin-right: 40px;\n}\n\n.u-spacing--triple > * + * {\n  margin-top: 60px;\n}\n\n.u-padding--triple {\n  padding: 60px;\n}\n\n.u-space--triple {\n  margin: 60px;\n}\n\n.u-padding--triple--top {\n  padding-top: 60px;\n}\n\n.u-space--triple--top {\n  margin-top: 60px;\n}\n\n.u-padding--triple--bottom {\n  padding-bottom: 60px;\n}\n\n.u-space--triple--bottom {\n  margin-bottom: 60px;\n}\n\n.u-padding--triple--left {\n  padding-left: 60px;\n}\n\n.u-space--triple--left {\n  margin-left: 60px;\n}\n\n.u-padding--triple--right {\n  padding-right: 60px;\n}\n\n.u-space--triple--right {\n  margin-right: 60px;\n}\n\n.u-spacing--quad > * + * {\n  margin-top: 80px;\n}\n\n.u-padding--quad {\n  padding: 80px;\n}\n\n.u-space--quad {\n  margin: 80px;\n}\n\n.u-padding--quad--top {\n  padding-top: 80px;\n}\n\n.u-space--quad--top {\n  margin-top: 80px;\n}\n\n.u-padding--quad--bottom {\n  padding-bottom: 80px;\n}\n\n.u-space--quad--bottom {\n  margin-bottom: 80px;\n}\n\n.u-padding--quad--left {\n  padding-left: 80px;\n}\n\n.u-space--quad--left {\n  margin-left: 80px;\n}\n\n.u-padding--quad--right {\n  padding-right: 80px;\n}\n\n.u-space--quad--right {\n  margin-right: 80px;\n}\n\n.u-spacing--zero > * + * {\n  margin-top: 0rem;\n}\n\n.u-padding--zero {\n  padding: 0rem;\n}\n\n.u-space--zero {\n  margin: 0rem;\n}\n\n.u-padding--zero--top {\n  padding-top: 0rem;\n}\n\n.u-space--zero--top {\n  margin-top: 0rem;\n}\n\n.u-padding--zero--bottom {\n  padding-bottom: 0rem;\n}\n\n.u-space--zero--bottom {\n  margin-bottom: 0rem;\n}\n\n.u-padding--zero--left {\n  padding-left: 0rem;\n}\n\n.u-space--zero--left {\n  margin-left: 0rem;\n}\n\n.u-padding--zero--right {\n  padding-right: 0rem;\n}\n\n.u-space--zero--right {\n  margin-right: 0rem;\n}\n\n.u-spacing--left > * + * {\n  margin-left: 20px;\n}\n\n/* ------------------------------------ *\\\n    $VENDORS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $TRUMPS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $HELPER/TRUMP CLASSES\n\\* ------------------------------------ */\n\n/**\n * Completely remove from the flow but leave available to screen readers.\n */\n\n.is-vishidden,\n.visually-hidden {\n  position: absolute !important;\n  overflow: hidden;\n  width: 1px;\n  height: 1px;\n  padding: 0;\n  border: 0;\n  clip: rect(1px, 1px, 1px, 1px);\n}\n\n/**\n * Hide elements only present and necessary for js enabled browsers.\n */\n\n.no-js .no-js-hide {\n  display: none;\n}\n\n.u-full-width {\n  width: 100%;\n}\n\n.u-align-center {\n  text-align: center;\n}\n\n.u-reversed-out {\n  color: #fff;\n}\n\n.u-reversed-out p,\n.u-reversed-out h1 a,\n.u-reversed-out h2 a,\n.u-reversed-out h3 a {\n  color: #fff;\n}\n\n/**\n * Remove all margins/padding\n */\n\n.u-no-spacing {\n  padding: 0;\n  margin: 0;\n}\n\n/**\n * Active on/off states\n */\n\n.u-active--off {\n  display: none;\n}\n\n[class*=\"-is-active\"].js-toggle-parent .u-active--on,\n[class*=\"-is-active\"].js-toggle .u-active--on {\n  display: none;\n}\n\n[class*=\"-is-active\"].js-toggle-parent .u-active--off,\n[class*=\"-is-active\"].js-toggle .u-active--off {\n  display: block;\n}\n\n[class*=\"-is-active\"] .u-hide-on-active {\n  display: none;\n}\n\n/**\n * Breakout content\n */\n\n.u-breakout {\n  margin-right: -20px;\n  margin-left: -20px;\n}\n\n@media (min-width: 1201px) {\n  .u-breakout {\n    margin-left: -40px;\n    margin-right: -40px;\n  }\n}\n\n/**\n * Justify left/right content\n */\n\n.u-split-content {\n  display: -webkit-box;\n  display: -ms-flexbox;\n  display: flex;\n}\n\n@media (max-width: 400px) {\n  .u-split-content {\n    -ms-flex-wrap: wrap;\n        flex-wrap: wrap;\n    -webkit-box-orient: vertical;\n    -webkit-box-direction: reverse;\n        -ms-flex-direction: column-reverse;\n            flex-direction: column-reverse;\n    -webkit-box-pack: end;\n        -ms-flex-pack: end;\n            justify-content: flex-end;\n  }\n\n  .u-split-content > * + * {\n    margin-bottom: 20px;\n  }\n}\n\n@media (min-width: 401px) {\n  .u-split-content {\n    -webkit-box-pack: justify;\n        -ms-flex-pack: justify;\n            justify-content: space-between;\n    -webkit-box-align: start;\n        -ms-flex-align: start;\n            align-items: flex-start;\n  }\n}\n\n", "", {"version":3,"sources":["/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/main.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/main.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_settings.variables.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_tools.mixins.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_tools.mq-tests.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_generic.reset.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.fonts.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.forms.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.headings.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.links.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.lists.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.main.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.media.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.tables.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_tools.include-media.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_base.text.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_layout.grids.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_layout.wrappers.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_objects.text.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_objects.buttons.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_components.blocks.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_components.cards.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_components.heros.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_components.sections.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_components.forms.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_objects.icons.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_objects.lists.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_objects.navs.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_objects.media.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_module.article.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_module.gallery.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_module.footer.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_module.header.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_module.main.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_modifier.animations.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_modifier.colors.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_modifier.display.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_modifier.spacing.scss","/Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/styles/resources/assets/styles/_trumps.helper-classes.scss"],"names":[],"mappings":"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hB00BH;CACF;;AgBv0BG;EATD;IAWK,wBAAA;GhB00BL;CACF;;AiBl9BD;;0CjBs9B0C;;AiBl9B1C;;GjBs9BG;;AiBn9BH;EACE,eAAA;EACA,mBAAA;EACA,oBAAA;EACA,YAAA;EACA,mBAAA;CjBs9BD;;AczcG;EGlhBJ;IAQI,mBAAA;IACA,oBAAA;GjBw9BD;CACF;;AiBr9BD;;;GjB09BG;;AiBr9BH;EACE,kBAAA;EACA,kBAAA;EACA,mBAAA;EACA,mBAAA;CjBw9BD;;AiBt9BC;EACE,kBAAA;CjBy9BH;;AiBr9BD;;GjBy9BG;;AiBr9BD;EAEI,gBAAA;CjBu9BL;;AiBz9BC;EAKI,iBAAA;CjBw9BL;;AiB79BC;EAQI,iBAAA;CjBy9BL;;AiBj+BC;EAQI,iBAAA;CjB69BL;;AiBr+BC;EAQI,iBAAA;CjBi+BL;;AiBz+BC;EAQI,iBAAA;CjBq+BL;;AiB7+BC;EAQI,iBAAA;CjBy+BL;;AiBj/BC;EAQI,iBAAA;CjB6+BL;;AiBr/BC;EAQI,iBAAA;CjBi/BL;;AiBz/BC;EAQI,iBAAA;CjBq/BL;;AiB7/BC;EAQI,iBAAA;CjBy/BL;;AiBjgCC;EAQI,iBAAA;CjB6/BL;;AiBrgCC;EAQI,kBAAA;CjBigCL;;AiBzgCC;EAQI,kBAAA;CjBqgCL;;AiB7gCC;EAQI,kBAAA;CjBygCL;;AiBjhCC;EAQI,kBAAA;CjB6gCL;;ACt9BD;;0CD09B0C;;AkBjkC1C;;0ClBqkC0C;;AkBjkC1C;;GlBqkCG;;AkBlkCH;EACE,kCAAA;ClBqkCD;;AkBlkCD;;EAEE,mCAAA;ClBqkCD;;AkBlkCD;;EAEE,oCAAA;ClBqkCD;;AkBlkCD;;GlBskCG;;AkBlkCH;EACE,qCAAA;ClBqkCD;;AkBlkCD;EACE,oCAAA;ClBqkCD;;AkBlkCD;EACE,oCAAA;ClBqkCD;;AkBlkCD;EACE,oCAAA;ClBqkCD;;AkBlkCD;EACE,qCAAA;ClBqkCD;;AkBlkCD;EACE,sCAAA;ClBqkCD;;AkBlkCD;;GlBskCG;;AkBnkCH;EACE,0BAAA;ClBskCD;;AkBnkCD;EACE,0BAAA;ClBskCD;;AkBnkCD;;GlBu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AqCj9DG;EACE,kBAAA;CrCo9DL;;AqCz9DG;EACE,oBAAA;CrC49DL;;AqCz9DG;EACE,mBAAA;CrC49DL;;AqCx+DC;EAEI,iBAAA;CrC0+DL;;AqCr+DG;EACE,cAAA;CrCw+DL;;AqCr+DG;EACE,aAAA;CrCw+DL;;AqC7+DG;EACE,kBAAA;CrCg/DL;;AqC7+DG;EACE,iBAAA;CrCg/DL;;AqCr/DG;EACE,qBAAA;CrCw/DL;;AqCr/DG;EACE,oBAAA;CrCw/DL;;AqC7/DG;EACE,mBAAA;CrCggEL;;AqC7/DG;EACE,kBAAA;CrCggEL;;AqCrgEG;EACE,oBAAA;CrCwgEL;;AqCrgEG;EACE,mBAAA;CrCwgEL;;AqCphEC;EAEI,iBAAA;CrCshEL;;AqCjhEG;EACE,cAAA;CrCohEL;;AqCjhEG;EACE,aAAA;CrCohEL;;AqCzhEG;EACE,kBAAA;CrC4hEL;;AqCzhEG;EACE,iBAAA;CrC4hEL;;AqCjiEG;EACE,qBAAA;CrCoiEL;;AqCjiEG;EACE,oBAAA;CrCoiEL;;AqCziEG;EACE,mBAAA;CrC4iEL;;AqCziEG;EACE,kBAAA;CrC4iEL;;AqCjjEG;EACE,oBAAA;CrCojEL;;AqCjjEG;EACE,mBAAA;CrCojEL;;AqChkEC;EAEI,iBAAA;CrCkkEL;;AqC7jEG;EACE,cAAA;CrCgkEL;;AqC7jEG;EACE,aAAA;CrCgkEL;;AqCrkEG;EACE,kBAAA;CrCwkEL;;AqCrkEG;EACE,iBAAA;CrCwkEL;;AqC7kEG;EACE,qBAAA;CrCglEL;;AqC7kEG;EACE,oBAAA;CrCglEL;;AqCrlEG;EACE,mBAAA;CrCwlEL;;AqCrlEG;EACE,kBAAA;CrCwlEL;;AqC7lEG;EACE,oBAAA;CrCgmEL;;AqC7lEG;EACE,mBAAA;CrCgmEL;;AqC5mEC;EAEI,iBAAA;CrC8mEL;;AqCzmEG;EACE,cAAA;CrC4mEL;;AqCzmEG;EACE,aAAA;CrC4mEL;;AqCjnEG;EACE,kBAAA;CrConEL;;AqCjnEG;EACE,iBAAA;CrConEL;;AqCznEG;EACE,qBAAA;CrC4nEL;;AqCznEG;EACE,oBAAA;CrC4nEL;;AqCjoEG;EACE,mBAAA;CrCooEL;;AqCjoEG;EACE,kBAAA;CrCooEL;;AqCzoEG;EACE,oBAAA;CrC4oEL;;AqCzoEG;EACE,mBAAA;CrC4oEL;;AqCxpEC;EAEI,iBAAA;CrC0pEL;;AqCrpEG;EACE,cAAA;CrCwpEL;;AqCrpEG;EACE,aAAA;CrCwpEL;;AqC7pEG;EACE,kBAAA;CrCgqEL;;AqC7pEG;EACE,iBAAA;CrCgqEL;;AqCrqEG;EACE,qBAAA;CrCwqEL;;AqCrqEG;EACE,oBAAA;CrCwqEL;;AqC7qEG;EACE,mBAAA;CrCgrEL;;AqC7qEG;EACE,kBAAA;CrCgrEL;;AqCrrEG;EACE,oBAAA;CrCwrEL;;AqCrrEG;EACE,mBAAA;CrCwrEL;;AqCnrED;EAEI,kBAAA;CrCqrEH;;AClkED;;0CDskE0C;;AClkE1C;;0CDskE0C;;AsCztE1C;;0CtC6tE0C;;AsCztE1C;;GtC6tEG;;AsC1tEH;;EAEE,8BAAA;EACA,iBAAA;EACA,WAAA;EACA,YAAA;EACA,WAAA;EACA,UAAA;EACA,+BAAA;CtC6tED;;AsC1tED;;GtC8tEG;;AsC3tEH;EACE,cAAA;CtC8tED;;AsC3tED;EACE,YAAA;CtC8tED;;AsC3tED;EACE,mBAAA;CtC8tED;;AsC3tED;EACE,YAAA;CtC8tED;;AsC/tED;;;;EAOI,YAAA;CtC+tEH;;AsC3tED;;GtC+tEG;;AsC5tEH;EACE,WAAA;EACA,UAAA;CtC+tED;;AsC5tED;;GtCguEG;;AsC7tEH;EACE,cAAA;CtCguED;;AAxkBD;;EsClpDI,cAAA;CtC+tEH;;AAzkBD;;EsClpDI,eAAA;CtCguEH;;AA1kBD;EsChpDI,cAAA;CtC8tEH;;AsC1tED;;GtC8tEG;;AsC3tEH;EACE,oBAAA;EACA,mBAAA;CtC8tED;;ActxDG;EwB1cJ;IAKI,mBAAA;IACA,oBAAA;GtCguED;CACF;;AsC7tED;;GtCiuEG;;AsC9tEH;EACE,qBAAA;EAAA,qBAAA;EAAA,cAAA;CtCiuED;;AcryDG;EwB7bJ;IAII,oBAAA;QAAA,gBAAA;IACA,6BAAA;IAAA,+BAAA;QAAA,mCAAA;YAAA,+BAAA;IACA,sBAAA;QAAA,mBAAA;YAAA,0BAAA;GtCmuED;;EsCzuEH;IASM,oBAAA;GtCouEH;CACF;;AcjzDG;EwB7bJ;IAeI,0BAAA;QAAA,uBAAA;YAAA,+BAAA;IACA,yBAAA;QAAA,sBAAA;YAAA,wBAAA;GtCouED;CACF","file":"main.scss","sourcesContent":["@charset \"UTF-8\";\n/**\n * CONTENTS\n *\n * SETTINGS\n * Variables............Globally-available variables and config.\n * Z-Index..............Semantic z-index manifest\n *\n * TOOLS\n * Mixins...............Useful mixins.\n * Include Media........Sass library for writing CSS media queries.\n * Media Query Test.....Displays the current breakport you're in.\n *\n * GENERIC\n * Reset................A level playing field.\n *\n * BASE\n * Forms................Common and default form styles.\n * Headings.............H1–H6 styles.\n * Links................Link styles.\n * Lists................Default list styles.\n * Main.................Page body defaults.\n * Media................Image and video styles.\n * Tables...............Default table styles.\n * Text.................Default text styles.\n *\n * LAYOUT\n * Grids................Grid/column classes.\n * Wrappers.............Wrapping/constraining elements.\n *\n * COMPONENTS\n * Blocks...............Modular components often consisting of text and media.\n * Cards................Modular components for mainly text and data (card-like).\n * Heros................Leading hero image/caption section of a content type.\n * Sections.............Larger components of pages.\n * Forms................Specific form styling.\n *\n * OBJECTS\n * Buttons..............Various button styles and styles.\n * Icons................Icon styles and settings.\n * Lists................Various site list styles.\n * Navs.................Site navigations.\n * Media................Specific media objects, e.g. figures\n *\n * TEXT\n * Text.................Various text-specific class definitions.\n *\n * PAGE STRUCTURE\n * Article..............Post-type pages with styled text.\n * Gallery..............Styles for all things gallery.\n * Footer...............The main page footer.\n * Header...............The main page header.\n * Main.................Content area styles.\n *\n * MODIFIERS\n * Animations...........Animation and transition effects.\n * Colors...............Text and background colors.\n * Display..............Show and hide and breakpoint visibility rules.\n * Spacings.............Padding and margins in classes.\n *\n * TRUMPS\n * Helper Classes.......Helper classes loaded last in the cascade.\n */\n/* ------------------------------------ *\\\n    $SETTINGS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $VARIABLES\n\\* ------------------------------------ */\n/**\n * Grid & Baseline Setup\n */\n/**\n * Theme Colors\n */\n/**\n * Default Colors\n */\n/**\n * Style Colors\n */\n/**\n * Typography\n */\n/**\n * Icons\n */\n/**\n * Common Breakpoints\n */\n/**\n * Animation\n */\n/**\n * Border Styles\n */\n/**\n * Default Spacing/Padding\n * Maintain a spacing system divisible by 10\n */\n/**\n * Font Sizes\n */\n/**\n * Native Custom Properties\n */\n@import url(\"https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,700;1,400;1,700&display=swap\");\n:root {\n  --body-font-size: 16px;\n  --font-size-xs: 13px;\n  --font-size-s: 16px;\n  --font-size-m: 20px;\n  --font-size-l: 22px;\n  --font-size-xl: 28px;\n  --font-size-xxl: 36px; }\n\n@media screen and (min-width: 700px) {\n  :root {\n    --font-size-xs: 14px;\n    --font-size-s: 18px;\n    --font-size-m: 22px;\n    --font-size-l: 24px;\n    --font-size-xl: 32px;\n    --font-size-xxl: 40px; } }\n\n/* ------------------------------------ *\\\n    $TOOLS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $MIXINS\n\\* ------------------------------------ */\n/**\n * Standard paragraph\n */\n/**\n * String interpolation function for SASS variables in SVG Image URI's\n */\n/* ------------------------------------ *\\\n    $MEDIA QUERY TESTS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $GENERIC\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $RESET\n\\* ------------------------------------ */\n/* Border-Box http:/paulirish.com/2012/box-sizing-border-box-ftw/ */\n*,\n*::before,\n*::after {\n  box-sizing: border-box; }\n\nbody {\n  margin: 0;\n  padding: 0; }\n\nblockquote,\nbody,\ndiv,\nfigure,\nfooter,\nform,\nh1,\nh2,\nh3,\nh4,\nh5,\nh6,\nheader,\nhtml,\niframe,\nlabel,\nlegend,\nli,\nnav,\nobject,\nol,\np,\nsection,\ntable,\nul {\n  margin: 0;\n  padding: 0; }\n\narticle,\nfigure,\nfooter,\nheader,\nhgroup,\nnav,\nsection {\n  display: block; }\n\naddress {\n  font-style: normal; }\n\n/* ------------------------------------ *\\\n    $BASE\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $FONTS\n\\* ------------------------------------ */\n@font-face {\n  font-family: 'Big John';\n  src: url(\"../fonts/big_john-webfont.woff2\") format(\"woff2\"), url(\"../fonts/big_john-webfont.woff\") format(\"woff\");\n  font-weight: normal;\n  font-style: normal; }\n\n@font-face {\n  font-family: 'Go Bold';\n  src: url(\"../fonts/gobold_regular_italic-webfont.woff2\") format(\"woff2\"), url(\"../fonts/gobold_regular_italic-webfont.woff\") format(\"woff\");\n  font-weight: normal;\n  font-style: normal; }\n\n/* ------------------------------------ *\\\n    $FORMS\n\\* ------------------------------------ */\nform ol,\nform ul {\n  list-style: none;\n  margin-left: 0; }\n\nlegend {\n  margin-bottom: 6px;\n  font-weight: bold; }\n\nfieldset {\n  border: 0;\n  padding: 0;\n  margin: 0;\n  min-width: 0; }\n\ninput,\nselect,\ntextarea {\n  width: 100%;\n  border: none;\n  appearance: none; }\n\ninput[type=text],\ninput[type=password],\ninput[type=email],\ninput[type=search],\ninput[type=tel],\nselect {\n  font-size: 16px;\n  font-family: \"Roboto\", sans-serif;\n  padding: 18px 20px;\n  box-shadow: none;\n  border: 1px solid #666; }\n  input[type=text]::placeholder,\n  input[type=password]::placeholder,\n  input[type=email]::placeholder,\n  input[type=search]::placeholder,\n  input[type=tel]::placeholder,\n  select::placeholder {\n    color: #666; }\n  input[type=text]:focus,\n  input[type=password]:focus,\n  input[type=email]:focus,\n  input[type=search]:focus,\n  input[type=tel]:focus,\n  select:focus {\n    outline: 2px solid #666; }\n\ninput[type=radio],\ninput[type=checkbox] {\n  outline: none;\n  margin: 0;\n  margin-right: 5px;\n  height: 18px;\n  width: 18px;\n  line-height: 1;\n  background-size: 18px;\n  background-repeat: no-repeat;\n  background-position: 0 0;\n  cursor: pointer;\n  display: block;\n  float: left;\n  border: 1px solid #666;\n  padding: 0;\n  user-select: none;\n  appearance: none;\n  background-color: #fff; }\n\ninput[type=radio] + label,\ninput[type=checkbox] + label {\n  display: inline-block;\n  cursor: pointer;\n  position: relative;\n  margin-bottom: 0; }\n\ninput[type=radio]:checked,\ninput[type=checkbox]:checked {\n  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3E%3Cpath d='M26.08,3.56l-2,1.95L10.61,19l-5-4L3.47,13.29,0,17.62l2.17,1.73L9.1,24.9,11,26.44l1.77-1.76L28.05,9.43,30,7.48Z' fill='%23ef4438'/%3E%3C/svg%3E\");\n  background-repeat: no-repeat;\n  background-position: center center;\n  background-size: 10px; }\n\ninput[type=radio] {\n  border-radius: 50px; }\n\ninput[type=checkbox] {\n  border-radius: 2px; }\n\ninput[type=submit] {\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1); }\n\n/* clears the 'X' from Internet Explorer */\ninput[type=search]::-ms-clear {\n  display: none;\n  width: 0;\n  height: 0; }\n\ninput[type=search]::-ms-reveal {\n  display: none;\n  width: 0;\n  height: 0; }\n\n/* clears the 'X' from Chrome */\ninput[type=\"search\"]::-webkit-search-decoration,\ninput[type=\"search\"]::-webkit-search-cancel-button,\ninput[type=\"search\"]::-webkit-search-results-button,\ninput[type=\"search\"]::-webkit-search-results-decoration {\n  display: none; }\n\n/* removes the blue background on Chrome's autocomplete */\ninput:-webkit-autofill,\ninput:-webkit-autofill:hover,\ninput:-webkit-autofill:focus,\ninput:-webkit-autofill:active {\n  -webkit-box-shadow: 0 0 0 30px white inset; }\n\nselect {\n  background-color: #fff;\n  appearance: none;\n  position: relative;\n  width: 100%;\n  padding-right: 30px; }\n\n/* ------------------------------------ *\\\n    $HEADINGS\n\\* ------------------------------------ */\nh1,\n.o-heading--xxl {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-xxl, 40px);\n  line-height: 1.3; }\n\nh2,\n.o-heading--xl {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-xl, 32px);\n  line-height: 1.3; }\n\nh3,\n.o-heading--l {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-l, 24px);\n  line-height: 1.4; }\n\nh4,\n.o-heading--m {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-m, 22px);\n  line-height: 1.4; }\n\nh5,\n.o-heading--s {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-s, 18px);\n  line-height: 1.6; }\n\nh6,\n.o-heading--xs {\n  font-family: \"Go Bold\", sans-serif;\n  font-size: var(--font-size-xs, 14px);\n  line-height: 1.5; }\n\n/* ------------------------------------ *\\\n    $LINKS\n\\* ------------------------------------ */\na {\n  text-decoration: none;\n  color: #ef4438;\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1); }\n  a:hover {\n    color: darker(#ef4438, 10%); }\n\n/* ------------------------------------ *\\\n    $LISTS\n\\* ------------------------------------ */\nol,\nul {\n  margin: 0;\n  padding: 0;\n  list-style: none; }\n\n/**\n * Definition Lists\n */\ndl {\n  overflow: hidden;\n  margin: 0 0 20px; }\n\ndt {\n  font-weight: bold; }\n\ndd {\n  margin-left: 0; }\n\n/* ------------------------------------ *\\\n    $SITE MAIN\n\\* ------------------------------------ */\nbody {\n  background: #fff;\n  font: 400 16px/1.3 \"Roboto\", sans-serif;\n  -webkit-text-size-adjust: 100%;\n  color: #000;\n  -webkit-font-smoothing: antialiased;\n  -moz-osx-font-smoothing: grayscale; }\n\n/* ------------------------------------ *\\\n    $MEDIA ELEMENTS\n\\* ------------------------------------ */\n/**\n * Flexible Media\n */\nimg,\nvideo,\nobject,\nsvg,\niframe {\n  max-width: 100%;\n  border: none;\n  display: block; }\n\nimg {\n  height: auto; }\n\nsvg {\n  max-height: 100%; }\n\npicture,\npicture img {\n  display: block; }\n\nfigure {\n  position: relative;\n  display: inline-block;\n  overflow: hidden; }\n\nfigcaption a {\n  display: block; }\n\n/* ------------------------------------ *\\\n    $PRINT STYLES\n\\* ------------------------------------ */\n@media print {\n  *,\n  *::before,\n  *::after,\n  *::first-letter,\n  *::first-line {\n    background: transparent !important;\n    color: black !important;\n    box-shadow: none !important;\n    text-shadow: none !important; }\n  a,\n  a:visited {\n    text-decoration: underline; }\n  a[href]::after {\n    content: \" (\" attr(href) \")\"; }\n  abbr[title]::after {\n    content: \" (\" attr(title) \")\"; }\n  /*\n   * Don't show links that are fragment identifiers,\n   * or use the `javascript:` pseudo protocol\n   */\n  a[href^=\"#\"]::after,\n  a[href^=\"javascript:\"]::after {\n    content: \"\"; }\n  pre,\n  blockquote {\n    border: 1px solid #999;\n    page-break-inside: avoid; }\n  /*\n   * Printing Tables:\n   * http://css-discuss.incutio.com/wiki/Printing_Tables\n   */\n  thead {\n    display: table-header-group; }\n  tr,\n  img {\n    page-break-inside: avoid; }\n  img {\n    max-width: 100% !important;\n    height: auto; }\n  p,\n  h2,\n  h3 {\n    orphans: 3;\n    widows: 3; }\n  h2,\n  h3 {\n    page-break-after: avoid; }\n  .no-print,\n  .c-main-header,\n  .c-main-footer,\n  .ad {\n    display: none; } }\n\n/* ------------------------------------ *\\\n    $TABLES\n\\* ------------------------------------ */\ntable {\n  border-collapse: collapse;\n  border-spacing: 0;\n  border: 1px solid #666;\n  width: 100%; }\n\nth {\n  text-align: left;\n  border: 1px solid transparent;\n  padding: 10px 0;\n  text-transform: uppercase;\n  vertical-align: top;\n  font-weight: bold; }\n\ntr {\n  border: 1px solid transparent; }\n\ntd {\n  border: 1px solid transparent;\n  padding: 10px; }\n\n/**\n * Responsive Table\n */\n.c-table--responsive {\n  border: 1px solid #666;\n  border-collapse: collapse;\n  padding: 0;\n  width: 100%; }\n  .c-table--responsive tr {\n    border: 1px solid #666;\n    background-color: #f0f0f0; }\n  .c-table--responsive th,\n  .c-table--responsive td {\n    padding: 10px; }\n  .c-table--responsive th {\n    font-size: var(--font-size-xs, 14px);\n    text-transform: uppercase;\n    border-bottom: 1px solid #666; }\n  @media (max-width: 700px) {\n    .c-table--responsive {\n      border: 0; }\n      .c-table--responsive thead {\n        border: none;\n        clip: rect(0 0 0 0);\n        height: 1px;\n        margin: -1px;\n        overflow: hidden;\n        padding: 0;\n        position: absolute;\n        width: 1px; }\n      .c-table--responsive tr {\n        border-bottom: 3px solid #666;\n        display: block;\n        margin-bottom: 10px; }\n        .c-table--responsive tr.this-is-active td:not(:first-child) {\n          display: flex; }\n        .c-table--responsive tr.this-is-active td:first-child::before {\n          content: \"- \" attr(data-label); }\n      .c-table--responsive td {\n        border-bottom: 1px solid #666;\n        display: flex;\n        align-items: center;\n        justify-content: space-between;\n        min-height: 40px; }\n        .c-table--responsive td:first-child {\n          cursor: pointer; }\n          .c-table--responsive td:first-child::before {\n            content: \"+ \" attr(data-label); }\n        .c-table--responsive td:last-child {\n          border-bottom: 0; }\n        .c-table--responsive td:not(:first-child) {\n          display: none; }\n        .c-table--responsive td::before {\n          content: attr(data-label);\n          font-weight: bold;\n          text-transform: uppercase;\n          font-size: var(--font-size-xs, 14px); } }\n\n/* ------------------------------------ *\\\n    $TEXT ELEMENTS\n\\* ------------------------------------ */\n/**\n * Text-Related Elements\n */\np {\n  line-height: 1.5;\n  font-family: \"Roboto\", sans-serif;\n  font-size: var(--body-font-size, 16px); }\n  @media print {\n    p {\n      font-size: 12px;\n      line-height: 1.3; } }\n\nsmall {\n  font-size: 90%; }\n\n/**\n * Bold\n */\nstrong,\nb {\n  font-weight: bold; }\n\n/**\n * Blockquote\n */\nblockquote {\n  display: flex;\n  flex-wrap: wrap; }\n  blockquote::before {\n    content: \"\\201C\";\n    font-family: \"Roboto\", sans-serif;\n    font-size: 40px;\n    line-height: 1;\n    color: #ef4438;\n    min-width: 40px;\n    border-right: 6px solid #666;\n    display: block;\n    margin-right: 20px; }\n  blockquote p {\n    line-height: 1.7;\n    flex: 1; }\n\n/**\n * Horizontal Rule\n */\nhr {\n  height: 1px;\n  border: none;\n  background-color: rgba(240, 240, 240, 0.5);\n  margin: 0 auto; }\n\n/**\n * Abbreviation\n */\nabbr {\n  border-bottom: 1px dotted #666;\n  cursor: help; }\n\n/* ------------------------------------ *\\\n    $LAYOUT\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $GRIDS\n\\* ------------------------------------ */\n.l-grid {\n  display: grid;\n  grid-template-rows: auto;\n  grid-column-gap: 20px;\n  grid-row-gap: 20px; }\n  @media all and (-ms-high-contrast: none) {\n    .l-grid {\n      display: flex;\n      flex-direction: row;\n      flex-wrap: wrap;\n      margin-left: -20px;\n      margin-right: -20px; }\n      .l-grid > * {\n        margin: 20px; } }\n  .l-grid-item {\n    position: relative; }\n  .l-grid--large-gutters {\n    grid-column-gap: 80px;\n    grid-row-gap: 80px; }\n  @media (min-width: 551px) {\n    .l-grid--2up {\n      grid-template-columns: repeat(2, 1fr); } }\n  @media all and (-ms-high-contrast: none) {\n    .l-grid--2up > * {\n      width: calc(50% - 40px); } }\n  .l-grid--2up--flex {\n    display: flex;\n    flex-wrap: wrap;\n    margin: 0 calc(20px * -1); }\n    .l-grid--2up--flex > * {\n      width: 100%;\n      padding-left: 20px;\n      padding-right: 20px;\n      margin-top: 40px; }\n      @media (min-width: 551px) {\n        .l-grid--2up--flex > * {\n          width: 50%; } }\n  @media (min-width: 551px) {\n    .l-grid--3up {\n      grid-template-columns: repeat(2, 1fr); } }\n  @media (min-width: 851px) {\n    .l-grid--3up {\n      grid-template-columns: repeat(3, 1fr); } }\n  @media all and (-ms-high-contrast: none) {\n    .l-grid--3up > * {\n      width: calc(33.333% - 40px); } }\n  .l-grid--4up {\n    grid-template-columns: repeat(minmax(200px, 1fr)); }\n    @media (min-width: 401px) {\n      .l-grid--4up {\n        grid-template-columns: repeat(2, 1fr); } }\n    @media (min-width: 701px) {\n      .l-grid--4up {\n        grid-template-columns: repeat(3, 1fr); } }\n    @media (min-width: 1001px) {\n      .l-grid--4up {\n        grid-template-columns: repeat(4, 1fr); } }\n    @media all and (-ms-high-contrast: none) {\n      .l-grid--4up > * {\n        width: calc(25% - 40px); } }\n  .l-grid--4up--at-medium {\n    grid-template-columns: repeat(2, 1fr); }\n    @media (min-width: 551px) {\n      .l-grid--4up--at-medium {\n        grid-template-columns: repeat(3, 1fr); } }\n    @media (min-width: 701px) {\n      .l-grid--4up--at-medium {\n        grid-template-columns: repeat(4, 1fr); } }\n    @media all and (-ms-high-contrast: none) {\n      .l-grid--4up--at-medium > * {\n        width: calc(25% - 40px); } }\n  .l-grid--5up {\n    grid-row-gap: 40px;\n    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }\n    @media (min-width: 851px) {\n      .l-grid--5up {\n        grid-template-columns: repeat(5, 1fr); } }\n    @media all and (-ms-high-contrast: none) {\n      .l-grid--5up > * {\n        width: calc(20% - 40px); } }\n\n/* ------------------------------------ *\\\n    $WRAPPERS & CONTAINERS\n\\* ------------------------------------ */\n/**\n * Wrapping element to keep content contained and centered.\n */\n.l-wrap {\n  margin: 0 auto;\n  padding-left: 20px;\n  padding-right: 20px;\n  width: 100%;\n  position: relative; }\n  @media (min-width: 1201px) {\n    .l-wrap {\n      padding-left: 40px;\n      padding-right: 40px; } }\n\n/**\n * Layout containers - keep content centered and within a maximum width. Also\n * adjusts left and right padding as the viewport widens.\n */\n.l-container {\n  max-width: 1200px;\n  margin-left: auto;\n  margin-right: auto;\n  position: relative; }\n  .l-container--xl {\n    max-width: 1600px; }\n\n/**\n * Grid classes\n */\n.l-container--1col {\n  max-width: 85px; }\n\n.l-container--2col {\n  max-width: 150px; }\n\n.l-container--3col {\n  max-width: 215px; }\n\n.l-container--4col {\n  max-width: 300px; }\n\n.l-container--5col {\n  max-width: 385px; }\n\n.l-container--6col {\n  max-width: 470px; }\n\n.l-container--7col {\n  max-width: 555px; }\n\n.l-container--8col {\n  max-width: 640px; }\n\n.l-container--9col {\n  max-width: 725px; }\n\n.l-container--10col {\n  max-width: 810px; }\n\n.l-container--11col {\n  max-width: 895px; }\n\n.l-container--12col {\n  max-width: 980px; }\n\n.l-container--13col {\n  max-width: 1065px; }\n\n.l-container--14col {\n  max-width: 1150px; }\n\n.l-container--15col {\n  max-width: 1235px; }\n\n.l-container--16col {\n  max-width: 1320px; }\n\n/* ------------------------------------ *\\\n    $TEXT\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $TEXT TYPES\n\\* ------------------------------------ */\n/**\n * Font Families\n */\n.u-font {\n  font-family: \"Roboto\", sans-serif; }\n\n.u-font--primary,\n.u-font--primary p {\n  font-family: \"Go Bold\", sans-serif; }\n\n.u-font--secondary,\n.u-font--secondary p {\n  font-family: \"Big John\", sans-serif; }\n\n/**\n * Text Sizes\n */\n.u-font--xs {\n  font-size: var(--font-size-xs, 14px); }\n\n.u-font--s {\n  font-size: var(--font-size-s, 18px); }\n\n.u-font--m {\n  font-size: var(--font-size-m, 22px); }\n\n.u-font--l {\n  font-size: var(--font-size-l, 24px); }\n\n.u-font--xl {\n  font-size: var(--font-size-xl, 32px); }\n\n.u-font--xxl {\n  font-size: var(--font-size-xxl, 40px); }\n\n/**\n * Text Transforms\n */\n.u-text-transform--upper {\n  text-transform: uppercase; }\n\n.u-text-transform--lower {\n  text-transform: lowercase; }\n\n/**\n * Text Styles\n */\n.u-text-style--italic {\n  font-style: italic; }\n\n.u-font-weight--normal {\n  font-weight: normal; }\n\n/**\n * Text Positioning\n */\n.u-align--center {\n  text-align: center; }\n\n/**\n * Text Decorations\n */\n.u-text-decoration--underline {\n  text-decoration: underline; }\n\n/**\n * Rich text editor text\n */\n.o-rte-text {\n  width: 100%;\n  margin: 0 auto; }\n  .o-rte-text > * + * {\n    margin-top: 20px; }\n  .o-rte-text > dl dd,\n  .o-rte-text > dl dt,\n  .o-rte-text > ol li,\n  .o-rte-text > ul li,\n  .o-rte-text > p {\n    line-height: 1.5;\n    font-family: \"Roboto\", sans-serif;\n    font-size: var(--body-font-size, 16px); }\n    @media print {\n      .o-rte-text > dl dd,\n      .o-rte-text > dl dt,\n      .o-rte-text > ol li,\n      .o-rte-text > ul li,\n      .o-rte-text > p {\n        font-size: 12px;\n        line-height: 1.3; } }\n  .o-rte-text > h1,\n  .o-rte-text > h2,\n  .o-rte-text > h3,\n  .o-rte-text > h4,\n  .o-rte-text > h5,\n  .o-rte-text > h6 {\n    padding-top: 20px;\n    margin-bottom: -10px; }\n  .o-rte-text h2:empty,\n  .o-rte-text h3:empty,\n  .o-rte-text p:empty {\n    display: none; }\n  .o-rte-text > h2 + h3 {\n    margin-top: 0;\n    padding-top: 10px; }\n  .o-rte-text a {\n    text-decoration: underline; }\n  .o-rte-text hr {\n    margin-top: 40px;\n    margin-bottom: 40px; }\n  .o-rte-text code,\n  .o-rte-text pre {\n    font-size: 125%; }\n  .o-rte-text ol,\n  .o-rte-text ul {\n    padding-left: 0;\n    margin-left: 0; }\n    .o-rte-text ol li,\n    .o-rte-text ul li {\n      list-style: none;\n      padding-left: 34px;\n      margin-left: 0;\n      position: relative;\n      line-height: 2.1em; }\n      .o-rte-text ol li::before,\n      .o-rte-text ul li::before {\n        color: #ef4438;\n        width: 10px;\n        display: inline-block;\n        position: absolute;\n        left: 0;\n        font-size: 24px;\n        line-height: 1;\n        top: 4px; }\n      .o-rte-text ol li li,\n      .o-rte-text ul li li {\n        list-style: none; }\n  .o-rte-text ol {\n    counter-reset: item; }\n    .o-rte-text ol li::before {\n      content: counter(item) \". \";\n      counter-increment: item; }\n    .o-rte-text ol li li {\n      counter-reset: item; }\n      .o-rte-text ol li li::before {\n        content: '\\002010'; }\n  .o-rte-text ul li::before {\n    content: '\\002022'; }\n  .o-rte-text ul li li::before {\n    content: '\\0025E6'; }\n\n/* ------------------------------------ *\\\n    $BUTTONS\n\\* ------------------------------------ */\n/**\n * Button Primary\n */\n.o-button--primary {\n  display: inline-flex;\n  position: relative;\n  justify-content: center;\n  align-items: center;\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  text-decoration: none;\n  border-radius: 0;\n  font-size: var(--body-font-size, 16px);\n  font-family: \"Go Bold\", sans-serif;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: 20px 40px;\n  text-transform: uppercase;\n  background-color: #ef4438;\n  color: #fff;\n  filter: brightness(100%); }\n  .o-button--primary:hover, .o-button--primary:focus {\n    filter: brightness(120%); }\n\n/**\n * Button Secondary\n */\n.o-button--secondary {\n  display: inline-flex;\n  position: relative;\n  justify-content: center;\n  align-items: center;\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  text-decoration: none;\n  border-radius: 0;\n  font-size: var(--body-font-size, 16px);\n  font-family: \"Go Bold\", sans-serif;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: 20px 40px;\n  text-transform: uppercase;\n  background-color: #ef4438;\n  color: #fff;\n  filter: brightness(100%); }\n  .o-button--secondary:hover, .o-button--secondary:focus {\n    filter: brightness(120%); }\n\nbutton,\ninput[type=\"submit\"],\n.o-button {\n  display: inline-flex;\n  position: relative;\n  justify-content: center;\n  align-items: center;\n  transition: all 0.23s cubic-bezier(0.86, 0, 0.07, 1);\n  text-decoration: none;\n  border-radius: 0;\n  font-size: var(--body-font-size, 16px);\n  font-family: \"Go Bold\", sans-serif;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: 20px 40px;\n  text-transform: uppercase;\n  background-color: #ef4438;\n  color: #fff;\n  filter: brightness(100%); }\n  button:hover, button:focus,\n  input[type=\"submit\"]:hover,\n  input[type=\"submit\"]:focus,\n  .o-button:hover,\n  .o-button:focus {\n    filter: brightness(120%); }\n\n/* ------------------------------------ *\\\n    $COMPONENTS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $BLOCKS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $CARDS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $HEROS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $PAGE SECTIONS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $SPECIFIC FORMS\n\\* ------------------------------------ */\n/**\n * Validation\n */\n.has-error {\n  border-color: #f00 !important; }\n\n.is-valid {\n  border-color: #089e00 !important; }\n\n/**\n * Newsletter Signup\n */\n.o-newsletter-signup {\n  display: flex;\n  flex-direction: column; }\n  @media (min-width: 701px) {\n    .o-newsletter-signup {\n      flex-direction: row; } }\n  .o-newsletter-signup input[type=email] {\n    width: 100%;\n    border: none; }\n    @media (min-width: 701px) {\n      .o-newsletter-signup input[type=email] {\n        width: calc(100% - 180px); } }\n  .o-newsletter-signup input[type=submit] {\n    width: 100%;\n    margin-top: 10px; }\n    @media (min-width: 701px) {\n      .o-newsletter-signup input[type=submit] {\n        margin-top: 0;\n        width: 180px; } }\n\n/* ------------------------------------ *\\\n    $OBJECTS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $ICONS\n\\* ------------------------------------ */\n/**\n * Icon Sizing\n */\n.o-icon {\n  display: inline-block; }\n\n.u-icon--xs {\n  width: 15px;\n  height: 15px; }\n\n.u-icon--s {\n  width: 20px;\n  height: 20px; }\n\n.u-icon--m {\n  width: 30px;\n  height: 30px; }\n\n.u-icon--l {\n  width: 40px;\n  height: 40px; }\n\n.u-icon--xl {\n  width: 70px;\n  height: 70px; }\n\n/* ------------------------------------ *\\\n    $LIST TYPES\n\\* ------------------------------------ */\n/**\n * Numbered List\n */\n.o-list--numbered {\n  counter-reset: item; }\n  .o-list--numbered li {\n    display: block; }\n    .o-list--numbered li::before {\n      content: counter(item);\n      counter-increment: item;\n      color: #fff;\n      padding: 10px 15px;\n      border-radius: 3px;\n      background-color: #000;\n      font-weight: bold;\n      margin-right: 20px;\n      float: left; }\n    .o-list--numbered li > * {\n      overflow: hidden; }\n    .o-list--numbered li li {\n      counter-reset: item; }\n      .o-list--numbered li li::before {\n        content: \"\\002010\"; }\n\n/**\n * Bullet List\n */\n.o-bullet-list {\n  list-style-type: disc;\n  padding-left: 20px; }\n  .o-bullet-list li {\n    overflow: visible; }\n    .o-bullet-list li:last-child {\n      margin-bottom: 0; }\n\n/* ------------------------------------ *\\\n    $NAVIGATION\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $MEDIA OBJECTS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $PAGE STRUCTURE\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $ARTICLE & RELATED COMPONENTS\n\\* ------------------------------------ */\n.page-id-5 {\n  height: 100vh;\n  display: flex;\n  flex-direction: column;\n  justify-content: space-between;\n  background-color: #fbc617; }\n  .page-id-5 .c-main-header {\n    background: url(\"../images/header-bkg.png\") center bottom no-repeat;\n    background-size: cover; }\n\n.l-article--landing {\n  padding: 0 0 40px 0;\n  max-width: 550px;\n  text-align: center; }\n\n/* ------------------------------------ *\\\n    $GALLERY\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $FOOTER\n\\* ------------------------------------ */\n.c-main-footer {\n  background-color: #ef4438;\n  color: #fff; }\n  .c-main-footer--inner {\n    padding: 10px 0;\n    text-align: center; }\n\n/* ------------------------------------ *\\\n    $HEADER\n\\* ------------------------------------ */\n.o-logo {\n  margin: 40px 20px 80px 20px; }\n  .o-logo img {\n    width: 100%;\n    height: auto;\n    max-width: 400px;\n    margin: 0 auto;\n    position: relative; }\n    @media (min-width: 701px) {\n      .o-logo img {\n        left: -15px; } }\n\n/* ------------------------------------ *\\\n    $MAIN CONTENT AREA\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $MODIFIERS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $ANIMATIONS & TRANSITIONS\n\\* ------------------------------------ */\n/**\n * Transitions\n */\n.has-trans {\n  transition: all 0.4s ease-in-out; }\n\n.has-trans--fast {\n  transition: all 0.1s ease-in-out; }\n\n.has-zoom {\n  overflow: hidden; }\n  .has-zoom img {\n    transition: transform 0.3s ease-out;\n    transform: scale(1); }\n  .has-zoom a:hover img {\n    transform: scale(1.03); }\n\n/**\n * Fade Classes\n */\n.has-fadeup {\n  opacity: 0;\n  transform: translate(0, 25px);\n  transition: all 0.6s ease-out 0.5s; }\n\n.fadeup,\n.has-fadeup.is-active {\n  opacity: 1;\n  transform: translate(0, 0); }\n\n.has-fadein {\n  opacity: 0;\n  transition: all 0.8s ease-out; }\n\n.fadein {\n  opacity: 1; }\n\n.lazyload,\n.lazyloading {\n  opacity: 0;\n  transform: translate(0, 25px);\n  transition: all 0.6s ease-out; }\n\n.lazyloaded {\n  opacity: 1;\n  transition: opacity 300ms; }\n\n@keyframes bounce {\n  0%,\n  100% {\n    transform: translateY(0); }\n  20% {\n    transform: translateY(-3px); }\n  80% {\n    transform: translateY(3px); } }\n\n/* ------------------------------------ *\\\n    $COLOR MODIFIERS\n\\* ------------------------------------ */\n/**\n * Text Colors\n */\n.u-color--black,\n.u-color--black a {\n  color: #000; }\n\n.u-color--gray,\n.u-color--gray a {\n  color: #666; }\n\n.u-color--gray--light,\n.u-color--gray--light a {\n  color: #f0f0f0; }\n\n.u-color--white,\n.u-color--white a {\n  color: #fff !important; }\n\n/**\n * Background Colors\n */\n.u-background-color--none {\n  background: none; }\n\n.u-background-color--black {\n  background-color: #000; }\n\n.u-background-color--gray {\n  background-color: #666; }\n\n.u-background-color--gray--light {\n  background-color: #f0f0f0; }\n\n.u-background-color--white {\n  background-color: #fff; }\n\n/**\n * SVG Fill Colors\n */\n.u-path-fill--black path {\n  fill: #000; }\n\n.u-path-fill--gray path {\n  fill: #666; }\n\n.u-path-fill--white path {\n  fill: #fff; }\n\n/* ------------------------------------ *\\\n    $DISPLAY STATES\n\\* ------------------------------------ */\n/**\n * Display Classes\n */\n.u-display--inline-block {\n  display: inline-block; }\n\n.u-display--block {\n  display: block; }\n\n.u-flex {\n  display: flex; }\n\n.u-justify-content--space-between {\n  justify-content: space-between; }\n\n.u-justify-content--flex-end {\n  justify-content: flex-end; }\n\n.u-align-items--center {\n  align-items: center; }\n\n.u-flex-directon--column {\n  flex-direction: column; }\n\n@media (max-width: 550px) {\n  .u-hide-until--s {\n    display: none; } }\n\n@media (max-width: 700px) {\n  .u-hide-until--m {\n    display: none; } }\n\n@media (max-width: 850px) {\n  .u-hide-until--l {\n    display: none; } }\n\n@media (max-width: 1000px) {\n  .u-hide-until--xl {\n    display: none; } }\n\n@media (min-width: 551px) {\n  .u-hide-after--s {\n    display: none; } }\n\n@media (min-width: 701px) {\n  .u-hide-after--m {\n    display: none; } }\n\n@media (min-width: 851px) {\n  .u-hide-after--l {\n    display: none; } }\n\n@media (min-width: 1001px) {\n  .u-hide-after--xl {\n    display: none; } }\n\n/* ------------------------------------ *\\\n    $SPACING\n\\* ------------------------------------ */\n.u-spacing > * + * {\n  margin-top: 20px; }\n\n.u-padding {\n  padding: 20px; }\n\n.u-space {\n  margin: 20px; }\n\n.u-padding--top {\n  padding-top: 20px; }\n\n.u-space--top {\n  margin-top: 20px; }\n\n.u-padding--bottom {\n  padding-bottom: 20px; }\n\n.u-space--bottom {\n  margin-bottom: 20px; }\n\n.u-padding--left {\n  padding-left: 20px; }\n\n.u-space--left {\n  margin-left: 20px; }\n\n.u-padding--right {\n  padding-right: 20px; }\n\n.u-space--right {\n  margin-right: 20px; }\n\n.u-spacing--quarter > * + * {\n  margin-top: 5px; }\n\n.u-padding--quarter {\n  padding: 5px; }\n\n.u-space--quarter {\n  margin: 5px; }\n\n.u-padding--quarter--top {\n  padding-top: 5px; }\n\n.u-space--quarter--top {\n  margin-top: 5px; }\n\n.u-padding--quarter--bottom {\n  padding-bottom: 5px; }\n\n.u-space--quarter--bottom {\n  margin-bottom: 5px; }\n\n.u-padding--quarter--left {\n  padding-left: 5px; }\n\n.u-space--quarter--left {\n  margin-left: 5px; }\n\n.u-padding--quarter--right {\n  padding-right: 5px; }\n\n.u-space--quarter--right {\n  margin-right: 5px; }\n\n.u-spacing--half > * + * {\n  margin-top: 10px; }\n\n.u-padding--half {\n  padding: 10px; }\n\n.u-space--half {\n  margin: 10px; }\n\n.u-padding--half--top {\n  padding-top: 10px; }\n\n.u-space--half--top {\n  margin-top: 10px; }\n\n.u-padding--half--bottom {\n  padding-bottom: 10px; }\n\n.u-space--half--bottom {\n  margin-bottom: 10px; }\n\n.u-padding--half--left {\n  padding-left: 10px; }\n\n.u-space--half--left {\n  margin-left: 10px; }\n\n.u-padding--half--right {\n  padding-right: 10px; }\n\n.u-space--half--right {\n  margin-right: 10px; }\n\n.u-spacing--and-half > * + * {\n  margin-top: 30px; }\n\n.u-padding--and-half {\n  padding: 30px; }\n\n.u-space--and-half {\n  margin: 30px; }\n\n.u-padding--and-half--top {\n  padding-top: 30px; }\n\n.u-space--and-half--top {\n  margin-top: 30px; }\n\n.u-padding--and-half--bottom {\n  padding-bottom: 30px; }\n\n.u-space--and-half--bottom {\n  margin-bottom: 30px; }\n\n.u-padding--and-half--left {\n  padding-left: 30px; }\n\n.u-space--and-half--left {\n  margin-left: 30px; }\n\n.u-padding--and-half--right {\n  padding-right: 30px; }\n\n.u-space--and-half--right {\n  margin-right: 30px; }\n\n.u-spacing--double > * + * {\n  margin-top: 40px; }\n\n.u-padding--double {\n  padding: 40px; }\n\n.u-space--double {\n  margin: 40px; }\n\n.u-padding--double--top {\n  padding-top: 40px; }\n\n.u-space--double--top {\n  margin-top: 40px; }\n\n.u-padding--double--bottom {\n  padding-bottom: 40px; }\n\n.u-space--double--bottom {\n  margin-bottom: 40px; }\n\n.u-padding--double--left {\n  padding-left: 40px; }\n\n.u-space--double--left {\n  margin-left: 40px; }\n\n.u-padding--double--right {\n  padding-right: 40px; }\n\n.u-space--double--right {\n  margin-right: 40px; }\n\n.u-spacing--triple > * + * {\n  margin-top: 60px; }\n\n.u-padding--triple {\n  padding: 60px; }\n\n.u-space--triple {\n  margin: 60px; }\n\n.u-padding--triple--top {\n  padding-top: 60px; }\n\n.u-space--triple--top {\n  margin-top: 60px; }\n\n.u-padding--triple--bottom {\n  padding-bottom: 60px; }\n\n.u-space--triple--bottom {\n  margin-bottom: 60px; }\n\n.u-padding--triple--left {\n  padding-left: 60px; }\n\n.u-space--triple--left {\n  margin-left: 60px; }\n\n.u-padding--triple--right {\n  padding-right: 60px; }\n\n.u-space--triple--right {\n  margin-right: 60px; }\n\n.u-spacing--quad > * + * {\n  margin-top: 80px; }\n\n.u-padding--quad {\n  padding: 80px; }\n\n.u-space--quad {\n  margin: 80px; }\n\n.u-padding--quad--top {\n  padding-top: 80px; }\n\n.u-space--quad--top {\n  margin-top: 80px; }\n\n.u-padding--quad--bottom {\n  padding-bottom: 80px; }\n\n.u-space--quad--bottom {\n  margin-bottom: 80px; }\n\n.u-padding--quad--left {\n  padding-left: 80px; }\n\n.u-space--quad--left {\n  margin-left: 80px; }\n\n.u-padding--quad--right {\n  padding-right: 80px; }\n\n.u-space--quad--right {\n  margin-right: 80px; }\n\n.u-spacing--zero > * + * {\n  margin-top: 0rem; }\n\n.u-padding--zero {\n  padding: 0rem; }\n\n.u-space--zero {\n  margin: 0rem; }\n\n.u-padding--zero--top {\n  padding-top: 0rem; }\n\n.u-space--zero--top {\n  margin-top: 0rem; }\n\n.u-padding--zero--bottom {\n  padding-bottom: 0rem; }\n\n.u-space--zero--bottom {\n  margin-bottom: 0rem; }\n\n.u-padding--zero--left {\n  padding-left: 0rem; }\n\n.u-space--zero--left {\n  margin-left: 0rem; }\n\n.u-padding--zero--right {\n  padding-right: 0rem; }\n\n.u-space--zero--right {\n  margin-right: 0rem; }\n\n.u-spacing--left > * + * {\n  margin-left: 20px; }\n\n/* ------------------------------------ *\\\n    $VENDORS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $TRUMPS\n\\* ------------------------------------ */\n/* ------------------------------------ *\\\n    $HELPER/TRUMP CLASSES\n\\* ------------------------------------ */\n/**\n * Completely remove from the flow but leave available to screen readers.\n */\n.is-vishidden,\n.visually-hidden {\n  position: absolute !important;\n  overflow: hidden;\n  width: 1px;\n  height: 1px;\n  padding: 0;\n  border: 0;\n  clip: rect(1px, 1px, 1px, 1px); }\n\n/**\n * Hide elements only present and necessary for js enabled browsers.\n */\n.no-js .no-js-hide {\n  display: none; }\n\n.u-full-width {\n  width: 100%; }\n\n.u-align-center {\n  text-align: center; }\n\n.u-reversed-out {\n  color: #fff; }\n  .u-reversed-out p,\n  .u-reversed-out h1 a,\n  .u-reversed-out h2 a,\n  .u-reversed-out h3 a {\n    color: #fff; }\n\n/**\n * Remove all margins/padding\n */\n.u-no-spacing {\n  padding: 0;\n  margin: 0; }\n\n/**\n * Active on/off states\n */\n.u-active--off {\n  display: none; }\n\n[class*=\"-is-active\"].js-toggle-parent .u-active--on,\n[class*=\"-is-active\"].js-toggle .u-active--on {\n  display: none; }\n\n[class*=\"-is-active\"].js-toggle-parent .u-active--off,\n[class*=\"-is-active\"].js-toggle .u-active--off {\n  display: block; }\n\n[class*=\"-is-active\"] .u-hide-on-active {\n  display: none; }\n\n/**\n * Breakout content\n */\n.u-breakout {\n  margin-right: -20px;\n  margin-left: -20px; }\n  @media (min-width: 1201px) {\n    .u-breakout {\n      margin-left: -40px;\n      margin-right: -40px; } }\n\n/**\n * Justify left/right content\n */\n.u-split-content {\n  display: flex; }\n  @media (max-width: 400px) {\n    .u-split-content {\n      flex-wrap: wrap;\n      flex-direction: column-reverse;\n      justify-content: flex-end; }\n      .u-split-content > * + * {\n        margin-bottom: 20px; } }\n  @media (min-width: 401px) {\n    .u-split-content {\n      justify-content: space-between;\n      align-items: flex-start; } }\n/*# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbInJlc291cmNlcy9hc3NldHMvc3R5bGVzL21haW4uc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19zZXR0aW5ncy52YXJpYWJsZXMuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19zZXR0aW5ncy56LWluZGV4LnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fdG9vbHMubWl4aW5zLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fdG9vbHMuaW5jbHVkZS1tZWRpYS5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX3Rvb2xzLm1xLXRlc3RzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fZ2VuZXJpYy5yZXNldC5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX2Jhc2UuZm9udHMuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19iYXNlLmZvcm1zLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fYmFzZS5oZWFkaW5ncy5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX2Jhc2UubGlua3Muc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19iYXNlLmxpc3RzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fYmFzZS5tYWluLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fYmFzZS5tZWRpYS5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX2Jhc2UudGFibGVzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fYmFzZS50ZXh0LnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fbGF5b3V0LmdyaWRzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fbGF5b3V0LndyYXBwZXJzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fb2JqZWN0cy50ZXh0LnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fb2JqZWN0cy5idXR0b25zLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fY29tcG9uZW50cy5ibG9ja3Muc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19jb21wb25lbnRzLmNhcmRzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fY29tcG9uZW50cy5oZXJvcy5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX2NvbXBvbmVudHMuc2VjdGlvbnMuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19jb21wb25lbnRzLmZvcm1zLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fb2JqZWN0cy5pY29ucy5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX29iamVjdHMubGlzdHMuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19vYmplY3RzLm5hdnMuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19vYmplY3RzLm1lZGlhLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fbW9kdWxlLmFydGljbGUuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19tb2R1bGUuZ2FsbGVyeS5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX21vZHVsZS5mb290ZXIuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19tb2R1bGUuaGVhZGVyLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fbW9kdWxlLm1haW4uc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL19tb2RpZmllci5hbmltYXRpb25zLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fbW9kaWZpZXIuY29sb3JzLnNjc3MiLCJyZXNvdXJjZXMvYXNzZXRzL3N0eWxlcy9fbW9kaWZpZXIuZGlzcGxheS5zY3NzIiwicmVzb3VyY2VzL2Fzc2V0cy9zdHlsZXMvX21vZGlmaWVyLnNwYWNpbmcuc2NzcyIsInJlc291cmNlcy9hc3NldHMvc3R5bGVzL190cnVtcHMuaGVscGVyLWNsYXNzZXMuc2NzcyJdLCJzb3VyY2VzQ29udGVudCI6WyIvKipcbiAqIENPTlRFTlRTXG4gKlxuICogU0VUVElOR1NcbiAqIFZhcmlhYmxlcy4uLi4uLi4uLi4uLkdsb2JhbGx5LWF2YWlsYWJsZSB2YXJpYWJsZXMgYW5kIGNvbmZpZy5cbiAqIFotSW5kZXguLi4uLi4uLi4uLi4uLlNlbWFudGljIHotaW5kZXggbWFuaWZlc3RcbiAqXG4gKiBUT09MU1xuICogTWl4aW5zLi4uLi4uLi4uLi4uLi4uVXNlZnVsIG1peGlucy5cbiAqIEluY2x1ZGUgTWVkaWEuLi4uLi4uLlNhc3MgbGlicmFyeSBmb3Igd3JpdGluZyBDU1MgbWVkaWEgcXVlcmllcy5cbiAqIE1lZGlhIFF1ZXJ5IFRlc3QuLi4uLkRpc3BsYXlzIHRoZSBjdXJyZW50IGJyZWFrcG9ydCB5b3UncmUgaW4uXG4gKlxuICogR0VORVJJQ1xuICogUmVzZXQuLi4uLi4uLi4uLi4uLi4uQSBsZXZlbCBwbGF5aW5nIGZpZWxkLlxuICpcbiAqIEJBU0VcbiAqIEZvcm1zLi4uLi4uLi4uLi4uLi4uLkNvbW1vbiBhbmQgZGVmYXVsdCBmb3JtIHN0eWxlcy5cbiAqIEhlYWRpbmdzLi4uLi4uLi4uLi4uLkgx4oCTSDYgc3R5bGVzLlxuICogTGlua3MuLi4uLi4uLi4uLi4uLi4uTGluayBzdHlsZXMuXG4gKiBMaXN0cy4uLi4uLi4uLi4uLi4uLi5EZWZhdWx0IGxpc3Qgc3R5bGVzLlxuICogTWFpbi4uLi4uLi4uLi4uLi4uLi4uUGFnZSBib2R5IGRlZmF1bHRzLlxuICogTWVkaWEuLi4uLi4uLi4uLi4uLi4uSW1hZ2UgYW5kIHZpZGVvIHN0eWxlcy5cbiAqIFRhYmxlcy4uLi4uLi4uLi4uLi4uLkRlZmF1bHQgdGFibGUgc3R5bGVzLlxuICogVGV4dC4uLi4uLi4uLi4uLi4uLi4uRGVmYXVsdCB0ZXh0IHN0eWxlcy5cbiAqXG4gKiBMQVlPVVRcbiAqIEdyaWRzLi4uLi4uLi4uLi4uLi4uLkdyaWQvY29sdW1uIGNsYXNzZXMuXG4gKiBXcmFwcGVycy4uLi4uLi4uLi4uLi5XcmFwcGluZy9jb25zdHJhaW5pbmcgZWxlbWVudHMuXG4gKlxuICogQ09NUE9ORU5UU1xuICogQmxvY2tzLi4uLi4uLi4uLi4uLi4uTW9kdWxhciBjb21wb25lbnRzIG9mdGVuIGNvbnNpc3Rpbmcgb2YgdGV4dCBhbmQgbWVkaWEuXG4gKiBDYXJkcy4uLi4uLi4uLi4uLi4uLi5Nb2R1bGFyIGNvbXBvbmVudHMgZm9yIG1haW5seSB0ZXh0IGFuZCBkYXRhIChjYXJkLWxpa2UpLlxuICogSGVyb3MuLi4uLi4uLi4uLi4uLi4uTGVhZGluZyBoZXJvIGltYWdlL2NhcHRpb24gc2VjdGlvbiBvZiBhIGNvbnRlbnQgdHlwZS5cbiAqIFNlY3Rpb25zLi4uLi4uLi4uLi4uLkxhcmdlciBjb21wb25lbnRzIG9mIHBhZ2VzLlxuICogRm9ybXMuLi4uLi4uLi4uLi4uLi4uU3BlY2lmaWMgZm9ybSBzdHlsaW5nLlxuICpcbiAqIE9CSkVDVFNcbiAqIEJ1dHRvbnMuLi4uLi4uLi4uLi4uLlZhcmlvdXMgYnV0dG9uIHN0eWxlcyBhbmQgc3R5bGVzLlxuICogSWNvbnMuLi4uLi4uLi4uLi4uLi4uSWNvbiBzdHlsZXMgYW5kIHNldHRpbmdzLlxuICogTGlzdHMuLi4uLi4uLi4uLi4uLi4uVmFyaW91cyBzaXRlIGxpc3Qgc3R5bGVzLlxuICogTmF2cy4uLi4uLi4uLi4uLi4uLi4uU2l0ZSBuYXZpZ2F0aW9ucy5cbiAqIE1lZGlhLi4uLi4uLi4uLi4uLi4uLlNwZWNpZmljIG1lZGlhIG9iamVjdHMsIGUuZy4gZmlndXJlc1xuICpcbiAqIFRFWFRcbiAqIFRleHQuLi4uLi4uLi4uLi4uLi4uLlZhcmlvdXMgdGV4dC1zcGVjaWZpYyBjbGFzcyBkZWZpbml0aW9ucy5cbiAqXG4gKiBQQUdFIFNUUlVDVFVSRVxuICogQXJ0aWNsZS4uLi4uLi4uLi4uLi4uUG9zdC10eXBlIHBhZ2VzIHdpdGggc3R5bGVkIHRleHQuXG4gKiBHYWxsZXJ5Li4uLi4uLi4uLi4uLi5TdHlsZXMgZm9yIGFsbCB0aGluZ3MgZ2FsbGVyeS5cbiAqIEZvb3Rlci4uLi4uLi4uLi4uLi4uLlRoZSBtYWluIHBhZ2UgZm9vdGVyLlxuICogSGVhZGVyLi4uLi4uLi4uLi4uLi4uVGhlIG1haW4gcGFnZSBoZWFkZXIuXG4gKiBNYWluLi4uLi4uLi4uLi4uLi4uLi5Db250ZW50IGFyZWEgc3R5bGVzLlxuICpcbiAqIE1PRElGSUVSU1xuICogQW5pbWF0aW9ucy4uLi4uLi4uLi4uQW5pbWF0aW9uIGFuZCB0cmFuc2l0aW9uIGVmZmVjdHMuXG4gKiBDb2xvcnMuLi4uLi4uLi4uLi4uLi5UZXh0IGFuZCBiYWNrZ3JvdW5kIGNvbG9ycy5cbiAqIERpc3BsYXkuLi4uLi4uLi4uLi4uLlNob3cgYW5kIGhpZGUgYW5kIGJyZWFrcG9pbnQgdmlzaWJpbGl0eSBydWxlcy5cbiAqIFNwYWNpbmdzLi4uLi4uLi4uLi4uLlBhZGRpbmcgYW5kIG1hcmdpbnMgaW4gY2xhc3Nlcy5cbiAqXG4gKiBUUlVNUFNcbiAqIEhlbHBlciBDbGFzc2VzLi4uLi4uLkhlbHBlciBjbGFzc2VzIGxvYWRlZCBsYXN0IGluIHRoZSBjYXNjYWRlLlxuICovXG5cbi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkU0VUVElOR1NcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbkBpbXBvcnQgXCJzZXR0aW5ncy52YXJpYWJsZXNcIjtcbkBpbXBvcnQgXCJzZXR0aW5ncy56LWluZGV4XCI7XG5cbi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkVE9PTFNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbkBpbXBvcnQgXCJ0b29scy5taXhpbnNcIjtcbkBpbXBvcnQgXCJ0b29scy5pbmNsdWRlLW1lZGlhXCI7XG5cbiR0ZXN0czogZmFsc2U7XG5cbkBpbXBvcnQgXCJ0b29scy5tcS10ZXN0c1wiO1xuXG4vKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJEdFTkVSSUNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbkBpbXBvcnQgXCJnZW5lcmljLnJlc2V0XCI7XG5cbi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkQkFTRVxuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuQGltcG9ydCBcImJhc2UuZm9udHNcIjtcbkBpbXBvcnQgXCJiYXNlLmZvcm1zXCI7XG5AaW1wb3J0IFwiYmFzZS5oZWFkaW5nc1wiO1xuQGltcG9ydCBcImJhc2UubGlua3NcIjtcbkBpbXBvcnQgXCJiYXNlLmxpc3RzXCI7XG5AaW1wb3J0IFwiYmFzZS5tYWluXCI7XG5AaW1wb3J0IFwiYmFzZS5tZWRpYVwiO1xuQGltcG9ydCBcImJhc2UudGFibGVzXCI7XG5AaW1wb3J0IFwiYmFzZS50ZXh0XCI7XG5cbi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkTEFZT1VUXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5AaW1wb3J0IFwibGF5b3V0LmdyaWRzXCI7XG5AaW1wb3J0IFwibGF5b3V0LndyYXBwZXJzXCI7XG5cbi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkVEVYVFxuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuQGltcG9ydCBcIm9iamVjdHMudGV4dFwiO1xuQGltcG9ydCBcIm9iamVjdHMuYnV0dG9uc1wiO1xuXG4vKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJENPTVBPTkVOVFNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbkBpbXBvcnQgXCJjb21wb25lbnRzLmJsb2Nrc1wiO1xuQGltcG9ydCBcImNvbXBvbmVudHMuY2FyZHNcIjtcbkBpbXBvcnQgXCJjb21wb25lbnRzLmhlcm9zXCI7XG5AaW1wb3J0IFwiY29tcG9uZW50cy5zZWN0aW9uc1wiO1xuQGltcG9ydCBcImNvbXBvbmVudHMuZm9ybXNcIjtcblxuLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRPQkpFQ1RTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5AaW1wb3J0IFwib2JqZWN0cy5pY29uc1wiO1xuQGltcG9ydCBcIm9iamVjdHMubGlzdHNcIjtcbkBpbXBvcnQgXCJvYmplY3RzLm5hdnNcIjtcbkBpbXBvcnQgXCJvYmplY3RzLm1lZGlhXCI7XG5cbi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkUEFHRSBTVFJVQ1RVUkVcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbkBpbXBvcnQgXCJtb2R1bGUuYXJ0aWNsZVwiO1xuQGltcG9ydCBcIm1vZHVsZS5nYWxsZXJ5XCI7XG5AaW1wb3J0IFwibW9kdWxlLmZvb3RlclwiO1xuQGltcG9ydCBcIm1vZHVsZS5oZWFkZXJcIjtcbkBpbXBvcnQgXCJtb2R1bGUubWFpblwiO1xuXG4vKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJE1PRElGSUVSU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuQGltcG9ydCBcIm1vZGlmaWVyLmFuaW1hdGlvbnNcIjtcbkBpbXBvcnQgXCJtb2RpZmllci5jb2xvcnNcIjtcbkBpbXBvcnQgXCJtb2RpZmllci5kaXNwbGF5XCI7XG5AaW1wb3J0IFwibW9kaWZpZXIuc3BhY2luZ1wiO1xuXG4vKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJFZFTkRPUlNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRUUlVNUFNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbkBpbXBvcnQgXCJ0cnVtcHMuaGVscGVyLWNsYXNzZXNcIjtcbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkVkFSSUFCTEVTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbi8qKlxuICogR3JpZCAmIEJhc2VsaW5lIFNldHVwXG4gKi9cbi8vIEdsb2JhbFxuJG1heC13aWR0aDogMTIwMHB4O1xuJG1heC13aWR0aC14bDogMTYwMHB4O1xuXG4vLyBHcmlkXG4kZ3JpZC1jb2x1bW5zOiAxNjtcbiRjb2wtd2lkdGg6IDY1O1xuJGd1dHRlcjogMjA7XG5cbi8qKlxuICogVGhlbWUgQ29sb3JzXG4gKi9cblxuLy8gTmV1dHJhbHNcbiRjLXdoaXRlOiAjZmZmO1xuJGMtZ3JheS0tbGlnaHQ6ICNmMGYwZjA7XG4kYy1ncmF5OiAjNjY2O1xuJGMtZ3JheS0tZGFyazogI2MwYzFjNTtcbiRjLWJsYWNrOiAjMDAwO1xuXG4kYy1wcmltYXJ5OiAjZmJjNjE3O1xuJGMtc2Vjb25kYXJ5OiAjZWY0NDM4O1xuJGMtdGVydGlhcnk6ICNkMWQ2Mjg7XG5cbi8qKlxuICogRGVmYXVsdCBDb2xvcnNcbiAqL1xuJGMtZXJyb3I6ICNmMDA7XG4kYy12YWxpZDogIzA4OWUwMDtcbiRjLXdhcm5pbmc6ICNmZmY2NjQ7XG4kYy1pbmZvcm1hdGlvbjogIzAwMGRiNTtcbiRjLW92ZXJsYXk6IHJnYmEoJGMtYmxhY2ssIDAuOCk7XG5cbi8qKlxuICogU3R5bGUgQ29sb3JzXG4gKi9cbiRjLWJvZHktY29sb3I6ICRjLWJsYWNrO1xuJGMtbGluay1jb2xvcjogJGMtc2Vjb25kYXJ5O1xuJGMtbGluay1ob3Zlci1jb2xvcjogZGFya2VyKCRjLXNlY29uZGFyeSwgMTAlKTtcbiRjLWJ1dHRvbi1wcmltYXJ5OiAkYy1zZWNvbmRhcnk7XG4kYy1idXR0b24tcHJpbWFyeS1ob3ZlcjogZGFya2VyKCRjLXNlY29uZGFyeSwgMTAlKTtcbiRjLWJ1dHRvbi1zZWNvbmRhcnk6ICRjLXRlcnRpYXJ5O1xuJGMtYnV0dG9uLXNlY29uZGFyeS1ob3ZlcjogZGFya2VyKCRjLXRlcnRpYXJ5LCAxMCUpO1xuJGMtYm9yZGVyOiAkYy1ncmF5O1xuXG4vKipcbiAqIFR5cG9ncmFwaHlcbiAqL1xuJGZmLWZvbnQ6IFwiUm9ib3RvXCIsIHNhbnMtc2VyaWY7XG4kZmYtZm9udC0tc2FuczogJGZmLWZvbnQ7XG4kZmYtZm9udC0tc2VyaWY6IHNlcmlmO1xuJGZmLWZvbnQtLW1vbm9zcGFjZTogTWVubG8sIE1vbmFjbywgXCJDb3VyaWVyIE5ld1wiLCBcIkNvdXJpZXJcIiwgbW9ub3NwYWNlO1xuXG4vLyBUaGVtZSB0eXBlZmFjZXNcbiRmZi1mb250LS1wcmltYXJ5OiBcIkdvIEJvbGRcIiwgc2Fucy1zZXJpZjtcbiRmZi1mb250LS1zZWNvbmRhcnk6IFwiQmlnIEpvaG5cIiwgc2Fucy1zZXJpZjtcblxuLyoqXG4gKiBJY29uc1xuICovXG4kaWNvbi14c21hbGw6IDE1cHg7XG4kaWNvbi1zbWFsbDogMjBweDtcbiRpY29uLW1lZGl1bTogMzBweDtcbiRpY29uLWxhcmdlOiA0MHB4O1xuJGljb24teGxhcmdlOiA3MHB4O1xuXG4vKipcbiAqIENvbW1vbiBCcmVha3BvaW50c1xuICovXG4keHNtYWxsOiA0MDBweDtcbiRzbWFsbDogNTUwcHg7XG4kbWVkaXVtOiA3MDBweDtcbiRsYXJnZTogODUwcHg7XG4keGxhcmdlOiAxMDAwcHg7XG4keHhsYXJnZTogMTIwMHB4O1xuJHh4eGxhcmdlOiAxNDAwcHg7XG5cbiRicmVha3BvaW50czogKFwieHNtYWxsXCI6ICR4c21hbGwsIFwic21hbGxcIjogJHNtYWxsLCBcIm1lZGl1bVwiOiAkbWVkaXVtLCBcImxhcmdlXCI6ICRsYXJnZSwgXCJ4bGFyZ2VcIjogJHhsYXJnZSwgXCJ4eGxhcmdlXCI6ICR4eGxhcmdlLCBcInh4eGxhcmdlXCI6ICR4eHhsYXJnZSk7XG5cbi8qKlxuICogQW5pbWF0aW9uXG4gKi9cbiRoYXJkLWVhc2UtaW46IGN1YmljLWJlemllcigwLjg2LCAwLCAwLjA3LCAxKTtcbiR0cmFuc2l0aW9uLWFsbDogYWxsIDAuMjNzICRoYXJkLWVhc2UtaW47XG5cbi8qKlxuICogQm9yZGVyIFN0eWxlc1xuICovXG4kYm9yZGVyLXJhZGl1cy1jdXJ2ZTogMnB4O1xuJGJvcmRlci1yYWRpdXMtY3VydmUtLWhhcmQ6IDRweDtcbiRib3JkZXItLXN0YW5kYXJkOiAxcHggc29saWQgJGMtYm9yZGVyO1xuJGJvcmRlci0tc3RhbmRhcmQtbGlnaHQ6IDJweCBzb2xpZCAkYy1ncmF5LS1saWdodDtcbiRib3gtc2hhZG93LS1zdGFuZGFyZDogMHB4IDRweCAxMnB4IHJnYmEoJGMtYmxhY2ssIDAuMDUpO1xuJGJveC1zaGFkb3ctLXRoaWNrOiAwcHggOHB4IDI0cHggcmdiYSgkYy1ibGFjaywgMC4yKTtcblxuLyoqXG4gKiBEZWZhdWx0IFNwYWNpbmcvUGFkZGluZ1xuICogTWFpbnRhaW4gYSBzcGFjaW5nIHN5c3RlbSBkaXZpc2libGUgYnkgMTBcbiAqL1xuJHNwYWNlOiAyMHB4O1xuJHNwYWNlLXF1YXJ0ZXI6ICRzcGFjZSAvIDQ7XG4kc3BhY2UtaGFsZjogJHNwYWNlIC8gMjtcbiRzcGFjZS1hbmQtaGFsZjogJHNwYWNlICogMS41O1xuJHNwYWNlLWRvdWJsZTogJHNwYWNlICogMjtcbiRzcGFjZS1kb3VibGUtaGFsZjogJHNwYWNlICogMi41O1xuJHNwYWNlLXRyaXBsZTogJHNwYWNlICogMztcbiRzcGFjZS1xdWFkOiAkc3BhY2UgKiA0O1xuXG4vKipcbiAqIEZvbnQgU2l6ZXNcbiAqL1xuXG4vKipcbiAqIE5hdGl2ZSBDdXN0b20gUHJvcGVydGllc1xuICovXG46cm9vdCB7XG4gIC0tYm9keS1mb250LXNpemU6IDE2cHg7XG4gIC0tZm9udC1zaXplLXhzOiAxM3B4O1xuICAtLWZvbnQtc2l6ZS1zOiAxNnB4O1xuICAtLWZvbnQtc2l6ZS1tOiAyMHB4O1xuICAtLWZvbnQtc2l6ZS1sOiAyMnB4O1xuICAtLWZvbnQtc2l6ZS14bDogMjhweDtcbiAgLS1mb250LXNpemUteHhsOiAzNnB4O1xufVxuXG4vLyBNZWRpdW0gQnJlYWtwb2ludFxuQG1lZGlhIHNjcmVlbiBhbmQgKG1pbi13aWR0aDogNzAwcHgpIHtcbiAgOnJvb3Qge1xuICAgIC0tZm9udC1zaXplLXhzOiAxNHB4O1xuICAgIC0tZm9udC1zaXplLXM6IDE4cHg7XG4gICAgLS1mb250LXNpemUtbTogMjJweDtcbiAgICAtLWZvbnQtc2l6ZS1sOiAyNHB4O1xuICAgIC0tZm9udC1zaXplLXhsOiAzMnB4O1xuICAgIC0tZm9udC1zaXplLXh4bDogNDBweDtcbiAgfVxufVxuXG4kYm9keS1mb250LXNpemU6IHZhcigtLWJvZHktZm9udC1zaXplLCAxNnB4KTtcbiRmb250LXNpemUteHM6IHZhcigtLWZvbnQtc2l6ZS14cywgMTRweCk7XG4kZm9udC1zaXplLXM6IHZhcigtLWZvbnQtc2l6ZS1zLCAxOHB4KTtcbiRmb250LXNpemUtbTogdmFyKC0tZm9udC1zaXplLW0sIDIycHgpO1xuJGZvbnQtc2l6ZS1sOiB2YXIoLS1mb250LXNpemUtbCwgMjRweCk7XG4kZm9udC1zaXplLXhsOiB2YXIoLS1mb250LXNpemUteGwsIDMycHgpO1xuJGZvbnQtc2l6ZS14eGw6IHZhcigtLWZvbnQtc2l6ZS14eGwsIDQwcHgpO1xuIiwiJHotaW5kZXgtdmFuaXNoOiAtMTtcbiR6LWluZGV4LW5vbmU6IDA7XG4kei1pbmRleC0xOiAxMDA7XG4kei1pbmRleC0yOiAyMDA7XG4kei1pbmRleC01OiA1MDA7XG4kei1pbmRleC0xMDogMTAwMDtcbiR6LWluZGV4LTE1OiAxNTAwO1xuJHotaW5kZXgtMzA6IDMwMDA7XG4kei1pbmRleC01MDogNTAwMDtcbiR6LWluZGV4LTc1OiA3NTAwO1xuJHotaW5kZXgtMTAwOiAxMDAwMDtcbiR6LWluZGV4LW1xLWRpc3BsYXk6ICR6LWluZGV4LTEwMDtcbiR6LWluZGV4LW1lbnUtdG9nZ2xlOiAkei1pbmRleC0xMDA7XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJE1JWElOU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4vKipcbiAqIFN0YW5kYXJkIHBhcmFncmFwaFxuICovXG5AbWl4aW4gcCB7XG4gIGxpbmUtaGVpZ2h0OiAxLjU7XG4gIGZvbnQtZmFtaWx5OiAkZmYtZm9udDtcbiAgZm9udC1zaXplOiAkYm9keS1mb250LXNpemU7XG5cbiAgQG1lZGlhIHByaW50IHtcbiAgICBmb250LXNpemU6IDEycHg7XG4gICAgbGluZS1oZWlnaHQ6IDEuMztcbiAgfVxufVxuXG4vKipcbiAqIFN0cmluZyBpbnRlcnBvbGF0aW9uIGZ1bmN0aW9uIGZvciBTQVNTIHZhcmlhYmxlcyBpbiBTVkcgSW1hZ2UgVVJJJ3NcbiAqL1xuQGZ1bmN0aW9uIHVybC1mcmllbmRseS1jb2xvcigkY29sb3IpIHtcbiAgQHJldHVybiBcIiUyM1wiICsgc3RyLXNsaWNlKFwiI3skY29sb3J9XCIsIDIsIC0xKTtcbn1cbiIsIkBjaGFyc2V0IFwiVVRGLThcIjtcblxuLy8gICAgIF8gICAgICAgICAgICBfICAgICAgICAgICBfICAgICAgICAgICAgICAgICAgICAgICAgICAgXyBfXG4vLyAgICAoXykgICAgICAgICAgfCB8ICAgICAgICAgfCB8ICAgICAgICAgICAgICAgICAgICAgICAgIHwgKF8pXG4vLyAgICAgXyBfIF9fICAgX19ffCB8XyAgIF8gIF9ffCB8IF9fXyAgIF8gX18gX19fICAgX19fICBfX3wgfF8gIF9fIF9cbi8vICAgIHwgfCAnXyBcXCAvIF9ffCB8IHwgfCB8LyBfYCB8LyBfIFxcIHwgJ18gYCBfIFxcIC8gXyBcXC8gX2AgfCB8LyBfYCB8XG4vLyAgICB8IHwgfCB8IHwgKF9ffCB8IHxffCB8IChffCB8ICBfXy8gfCB8IHwgfCB8IHwgIF9fLyAoX3wgfCB8IChffCB8XG4vLyAgICB8X3xffCB8X3xcXF9fX3xffFxcX18sX3xcXF9fLF98XFxfX198IHxffCB8X3wgfF98XFxfX198XFxfXyxffF98XFxfXyxffFxuLy9cbi8vICAgICAgU2ltcGxlLCBlbGVnYW50IGFuZCBtYWludGFpbmFibGUgbWVkaWEgcXVlcmllcyBpbiBTYXNzXG4vLyAgICAgICAgICAgICAgICAgICAgICAgIHYxLjQuOVxuLy9cbi8vICAgICAgICAgICAgICAgIGh0dHA6Ly9pbmNsdWRlLW1lZGlhLmNvbVxuLy9cbi8vICAgICAgICAgQXV0aG9yczogRWR1YXJkbyBCb3VjYXMgKEBlZHVhcmRvYm91Y2FzKVxuLy8gICAgICAgICAgICAgICAgICBIdWdvIEdpcmF1ZGVsIChAaHVnb2dpcmF1ZGVsKVxuLy9cbi8vICAgICAgVGhpcyBwcm9qZWN0IGlzIGxpY2Vuc2VkIHVuZGVyIHRoZSB0ZXJtcyBvZiB0aGUgTUlUIGxpY2Vuc2VcblxuLy8vL1xuLy8vIGluY2x1ZGUtbWVkaWEgbGlicmFyeSBwdWJsaWMgY29uZmlndXJhdGlvblxuLy8vIEBhdXRob3IgRWR1YXJkbyBCb3VjYXNcbi8vLyBAYWNjZXNzIHB1YmxpY1xuLy8vL1xuXG4vLy9cbi8vLyBDcmVhdGVzIGEgbGlzdCBvZiBnbG9iYWwgYnJlYWtwb2ludHNcbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBDcmVhdGVzIGEgc2luZ2xlIGJyZWFrcG9pbnQgd2l0aCB0aGUgbGFiZWwgYHBob25lYFxuLy8vICAkYnJlYWtwb2ludHM6ICgncGhvbmUnOiAzMjBweCk7XG4vLy9cbiRicmVha3BvaW50czogKFxuICAncGhvbmUnOiAzMjBweCxcbiAgJ3RhYmxldCc6IDc2OHB4LFxuICAnZGVza3RvcCc6IDEwMjRweFxuKSAhZGVmYXVsdDtcblxuLy8vXG4vLy8gQ3JlYXRlcyBhIGxpc3Qgb2Ygc3RhdGljIGV4cHJlc3Npb25zIG9yIG1lZGlhIHR5cGVzXG4vLy9cbi8vLyBAZXhhbXBsZSBzY3NzIC0gQ3JlYXRlcyBhIHNpbmdsZSBtZWRpYSB0eXBlIChzY3JlZW4pXG4vLy8gICRtZWRpYS1leHByZXNzaW9uczogKCdzY3JlZW4nOiAnc2NyZWVuJyk7XG4vLy9cbi8vLyBAZXhhbXBsZSBzY3NzIC0gQ3JlYXRlcyBhIHN0YXRpYyBleHByZXNzaW9uIHdpdGggbG9naWNhbCBkaXNqdW5jdGlvbiAoT1Igb3BlcmF0b3IpXG4vLy8gICRtZWRpYS1leHByZXNzaW9uczogKFxuLy8vICAgICdyZXRpbmEyeCc6ICcoLXdlYmtpdC1taW4tZGV2aWNlLXBpeGVsLXJhdGlvOiAyKSwgKG1pbi1yZXNvbHV0aW9uOiAxOTJkcGkpJ1xuLy8vICApO1xuLy8vXG4kbWVkaWEtZXhwcmVzc2lvbnM6IChcbiAgJ3NjcmVlbic6ICdzY3JlZW4nLFxuICAncHJpbnQnOiAncHJpbnQnLFxuICAnaGFuZGhlbGQnOiAnaGFuZGhlbGQnLFxuICAnbGFuZHNjYXBlJzogJyhvcmllbnRhdGlvbjogbGFuZHNjYXBlKScsXG4gICdwb3J0cmFpdCc6ICcob3JpZW50YXRpb246IHBvcnRyYWl0KScsXG4gICdyZXRpbmEyeCc6ICcoLXdlYmtpdC1taW4tZGV2aWNlLXBpeGVsLXJhdGlvOiAyKSwgKG1pbi1yZXNvbHV0aW9uOiAxOTJkcGkpLCAobWluLXJlc29sdXRpb246IDJkcHB4KScsXG4gICdyZXRpbmEzeCc6ICcoLXdlYmtpdC1taW4tZGV2aWNlLXBpeGVsLXJhdGlvOiAzKSwgKG1pbi1yZXNvbHV0aW9uOiAzNTBkcGkpLCAobWluLXJlc29sdXRpb246IDNkcHB4KSdcbikgIWRlZmF1bHQ7XG5cbi8vL1xuLy8vIERlZmluZXMgYSBudW1iZXIgdG8gYmUgYWRkZWQgb3Igc3VidHJhY3RlZCBmcm9tIGVhY2ggdW5pdCB3aGVuIGRlY2xhcmluZyBicmVha3BvaW50cyB3aXRoIGV4Y2x1c2l2ZSBpbnRlcnZhbHNcbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBJbnRlcnZhbCBmb3IgcGl4ZWxzIGlzIGRlZmluZWQgYXMgYDFgIGJ5IGRlZmF1bHRcbi8vLyAgQGluY2x1ZGUgbWVkaWEoJz4xMjhweCcpIHt9XG4vLy9cbi8vLyAgLyogR2VuZXJhdGVzOiAqL1xuLy8vICBAbWVkaWEobWluLXdpZHRoOiAxMjlweCkge31cbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBJbnRlcnZhbCBmb3IgZW1zIGlzIGRlZmluZWQgYXMgYDAuMDFgIGJ5IGRlZmF1bHRcbi8vLyAgQGluY2x1ZGUgbWVkaWEoJz4yMGVtJykge31cbi8vL1xuLy8vICAvKiBHZW5lcmF0ZXM6ICovXG4vLy8gIEBtZWRpYShtaW4td2lkdGg6IDIwLjAxZW0pIHt9XG4vLy9cbi8vLyBAZXhhbXBsZSBzY3NzIC0gSW50ZXJ2YWwgZm9yIHJlbXMgaXMgZGVmaW5lZCBhcyBgMC4xYCBieSBkZWZhdWx0LCB0byBiZSB1c2VkIHdpdGggYGZvbnQtc2l6ZTogNjIuNSU7YFxuLy8vICBAaW5jbHVkZSBtZWRpYSgnPjIuMHJlbScpIHt9XG4vLy9cbi8vLyAgLyogR2VuZXJhdGVzOiAqL1xuLy8vICBAbWVkaWEobWluLXdpZHRoOiAyLjFyZW0pIHt9XG4vLy9cbiR1bml0LWludGVydmFsczogKFxuICAncHgnOiAxLFxuICAnZW0nOiAwLjAxLFxuICAncmVtJzogMC4xLFxuICAnJzogMFxuKSAhZGVmYXVsdDtcblxuLy8vXG4vLy8gRGVmaW5lcyB3aGV0aGVyIHN1cHBvcnQgZm9yIG1lZGlhIHF1ZXJpZXMgaXMgYXZhaWxhYmxlLCB1c2VmdWwgZm9yIGNyZWF0aW5nIHNlcGFyYXRlIHN0eWxlc2hlZXRzXG4vLy8gZm9yIGJyb3dzZXJzIHRoYXQgZG9uJ3Qgc3VwcG9ydCBtZWRpYSBxdWVyaWVzLlxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIERpc2FibGVzIHN1cHBvcnQgZm9yIG1lZGlhIHF1ZXJpZXNcbi8vLyAgJGltLW1lZGlhLXN1cHBvcnQ6IGZhbHNlO1xuLy8vICBAaW5jbHVkZSBtZWRpYSgnPj10YWJsZXQnKSB7XG4vLy8gICAgLmZvbyB7XG4vLy8gICAgICBjb2xvcjogdG9tYXRvO1xuLy8vICAgIH1cbi8vLyAgfVxuLy8vXG4vLy8gIC8qIEdlbmVyYXRlczogKi9cbi8vLyAgLmZvbyB7XG4vLy8gICAgY29sb3I6IHRvbWF0bztcbi8vLyAgfVxuLy8vXG4kaW0tbWVkaWEtc3VwcG9ydDogdHJ1ZSAhZGVmYXVsdDtcblxuLy8vXG4vLy8gU2VsZWN0cyB3aGljaCBicmVha3BvaW50IHRvIGVtdWxhdGUgd2hlbiBzdXBwb3J0IGZvciBtZWRpYSBxdWVyaWVzIGlzIGRpc2FibGVkLiBNZWRpYSBxdWVyaWVzIHRoYXQgc3RhcnQgYXQgb3Jcbi8vLyBpbnRlcmNlcHQgdGhlIGJyZWFrcG9pbnQgd2lsbCBiZSBkaXNwbGF5ZWQsIGFueSBvdGhlcnMgd2lsbCBiZSBpZ25vcmVkLlxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIFRoaXMgbWVkaWEgcXVlcnkgd2lsbCBzaG93IGJlY2F1c2UgaXQgaW50ZXJjZXB0cyB0aGUgc3RhdGljIGJyZWFrcG9pbnRcbi8vLyAgJGltLW1lZGlhLXN1cHBvcnQ6IGZhbHNlO1xuLy8vICAkaW0tbm8tbWVkaWEtYnJlYWtwb2ludDogJ2Rlc2t0b3AnO1xuLy8vICBAaW5jbHVkZSBtZWRpYSgnPj10YWJsZXQnKSB7XG4vLy8gICAgLmZvbyB7XG4vLy8gICAgICBjb2xvcjogdG9tYXRvO1xuLy8vICAgIH1cbi8vLyAgfVxuLy8vXG4vLy8gIC8qIEdlbmVyYXRlczogKi9cbi8vLyAgLmZvbyB7XG4vLy8gICAgY29sb3I6IHRvbWF0bztcbi8vLyAgfVxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIFRoaXMgbWVkaWEgcXVlcnkgd2lsbCBOT1Qgc2hvdyBiZWNhdXNlIGl0IGRvZXMgbm90IGludGVyY2VwdCB0aGUgZGVza3RvcCBicmVha3BvaW50XG4vLy8gICRpbS1tZWRpYS1zdXBwb3J0OiBmYWxzZTtcbi8vLyAgJGltLW5vLW1lZGlhLWJyZWFrcG9pbnQ6ICd0YWJsZXQnO1xuLy8vICBAaW5jbHVkZSBtZWRpYSgnPj1kZXNrdG9wJykge1xuLy8vICAgIC5mb28ge1xuLy8vICAgICAgY29sb3I6IHRvbWF0bztcbi8vLyAgICB9XG4vLy8gIH1cbi8vL1xuLy8vICAvKiBObyBvdXRwdXQgKi9cbi8vL1xuJGltLW5vLW1lZGlhLWJyZWFrcG9pbnQ6ICdkZXNrdG9wJyAhZGVmYXVsdDtcblxuLy8vXG4vLy8gU2VsZWN0cyB3aGljaCBtZWRpYSBleHByZXNzaW9ucyBhcmUgYWxsb3dlZCBpbiBhbiBleHByZXNzaW9uIGZvciBpdCB0byBiZSB1c2VkIHdoZW4gbWVkaWEgcXVlcmllc1xuLy8vIGFyZSBub3Qgc3VwcG9ydGVkLlxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIFRoaXMgbWVkaWEgcXVlcnkgd2lsbCBzaG93IGJlY2F1c2UgaXQgaW50ZXJjZXB0cyB0aGUgc3RhdGljIGJyZWFrcG9pbnQgYW5kIGNvbnRhaW5zIG9ubHkgYWNjZXB0ZWQgbWVkaWEgZXhwcmVzc2lvbnNcbi8vLyAgJGltLW1lZGlhLXN1cHBvcnQ6IGZhbHNlO1xuLy8vICAkaW0tbm8tbWVkaWEtYnJlYWtwb2ludDogJ2Rlc2t0b3AnO1xuLy8vICAkaW0tbm8tbWVkaWEtZXhwcmVzc2lvbnM6ICgnc2NyZWVuJyk7XG4vLy8gIEBpbmNsdWRlIG1lZGlhKCc+PXRhYmxldCcsICdzY3JlZW4nKSB7XG4vLy8gICAgLmZvbyB7XG4vLy8gICAgICBjb2xvcjogdG9tYXRvO1xuLy8vICAgIH1cbi8vLyAgfVxuLy8vXG4vLy8gICAvKiBHZW5lcmF0ZXM6ICovXG4vLy8gICAuZm9vIHtcbi8vLyAgICAgY29sb3I6IHRvbWF0bztcbi8vLyAgIH1cbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBUaGlzIG1lZGlhIHF1ZXJ5IHdpbGwgTk9UIHNob3cgYmVjYXVzZSBpdCBpbnRlcmNlcHRzIHRoZSBzdGF0aWMgYnJlYWtwb2ludCBidXQgY29udGFpbnMgYSBtZWRpYSBleHByZXNzaW9uIHRoYXQgaXMgbm90IGFjY2VwdGVkXG4vLy8gICRpbS1tZWRpYS1zdXBwb3J0OiBmYWxzZTtcbi8vLyAgJGltLW5vLW1lZGlhLWJyZWFrcG9pbnQ6ICdkZXNrdG9wJztcbi8vLyAgJGltLW5vLW1lZGlhLWV4cHJlc3Npb25zOiAoJ3NjcmVlbicpO1xuLy8vICBAaW5jbHVkZSBtZWRpYSgnPj10YWJsZXQnLCAncmV0aW5hMngnKSB7XG4vLy8gICAgLmZvbyB7XG4vLy8gICAgICBjb2xvcjogdG9tYXRvO1xuLy8vICAgIH1cbi8vLyAgfVxuLy8vXG4vLy8gIC8qIE5vIG91dHB1dCAqL1xuLy8vXG4kaW0tbm8tbWVkaWEtZXhwcmVzc2lvbnM6ICgnc2NyZWVuJywgJ3BvcnRyYWl0JywgJ2xhbmRzY2FwZScpICFkZWZhdWx0O1xuXG4vLy8vXG4vLy8gQ3Jvc3MtZW5naW5lIGxvZ2dpbmcgZW5naW5lXG4vLy8gQGF1dGhvciBIdWdvIEdpcmF1ZGVsXG4vLy8gQGFjY2VzcyBwcml2YXRlXG4vLy8vXG5cblxuLy8vXG4vLy8gTG9nIGEgbWVzc2FnZSBlaXRoZXIgd2l0aCBgQGVycm9yYCBpZiBzdXBwb3J0ZWRcbi8vLyBlbHNlIHdpdGggYEB3YXJuYCwgdXNpbmcgYGZlYXR1cmUtZXhpc3RzKCdhdC1lcnJvcicpYFxuLy8vIHRvIGRldGVjdCBzdXBwb3J0LlxuLy8vXG4vLy8gQHBhcmFtIHtTdHJpbmd9ICRtZXNzYWdlIC0gTWVzc2FnZSB0byBsb2dcbi8vL1xuQGZ1bmN0aW9uIGltLWxvZygkbWVzc2FnZSkge1xuICBAaWYgZmVhdHVyZS1leGlzdHMoJ2F0LWVycm9yJykge1xuICAgIEBlcnJvciAkbWVzc2FnZTtcbiAgfVxuXG4gIEBlbHNlIHtcbiAgICBAd2FybiAkbWVzc2FnZTtcbiAgICAkXzogbm9vcCgpO1xuICB9XG5cbiAgQHJldHVybiAkbWVzc2FnZTtcbn1cblxuLy8vXG4vLy8gRGV0ZXJtaW5lcyB3aGV0aGVyIGEgbGlzdCBvZiBjb25kaXRpb25zIGlzIGludGVyY2VwdGVkIGJ5IHRoZSBzdGF0aWMgYnJlYWtwb2ludC5cbi8vL1xuLy8vIEBwYXJhbSB7QXJnbGlzdH0gICAkY29uZGl0aW9ucyAgLSBNZWRpYSBxdWVyeSBjb25kaXRpb25zXG4vLy9cbi8vLyBAcmV0dXJuIHtCb29sZWFufSAtIFJldHVybnMgdHJ1ZSBpZiB0aGUgY29uZGl0aW9ucyBhcmUgaW50ZXJjZXB0ZWQgYnkgdGhlIHN0YXRpYyBicmVha3BvaW50XG4vLy9cbkBmdW5jdGlvbiBpbS1pbnRlcmNlcHRzLXN0YXRpYy1icmVha3BvaW50KCRjb25kaXRpb25zLi4uKSB7XG4gICRuby1tZWRpYS1icmVha3BvaW50LXZhbHVlOiBtYXAtZ2V0KCRicmVha3BvaW50cywgJGltLW5vLW1lZGlhLWJyZWFrcG9pbnQpO1xuXG4gIEBlYWNoICRjb25kaXRpb24gaW4gJGNvbmRpdGlvbnMge1xuICAgIEBpZiBub3QgbWFwLWhhcy1rZXkoJG1lZGlhLWV4cHJlc3Npb25zLCAkY29uZGl0aW9uKSB7XG4gICAgICAkb3BlcmF0b3I6IGdldC1leHByZXNzaW9uLW9wZXJhdG9yKCRjb25kaXRpb24pO1xuICAgICAgJHByZWZpeDogZ2V0LWV4cHJlc3Npb24tcHJlZml4KCRvcGVyYXRvcik7XG4gICAgICAkdmFsdWU6IGdldC1leHByZXNzaW9uLXZhbHVlKCRjb25kaXRpb24sICRvcGVyYXRvcik7XG5cbiAgICAgIEBpZiAoJHByZWZpeCA9PSAnbWF4JyBhbmQgJHZhbHVlIDw9ICRuby1tZWRpYS1icmVha3BvaW50LXZhbHVlKSBvciAoJHByZWZpeCA9PSAnbWluJyBhbmQgJHZhbHVlID4gJG5vLW1lZGlhLWJyZWFrcG9pbnQtdmFsdWUpIHtcbiAgICAgICAgQHJldHVybiBmYWxzZTtcbiAgICAgIH1cbiAgICB9XG5cbiAgICBAZWxzZSBpZiBub3QgaW5kZXgoJGltLW5vLW1lZGlhLWV4cHJlc3Npb25zLCAkY29uZGl0aW9uKSB7XG4gICAgICBAcmV0dXJuIGZhbHNlO1xuICAgIH1cbiAgfVxuXG4gIEByZXR1cm4gdHJ1ZTtcbn1cblxuLy8vL1xuLy8vIFBhcnNpbmcgZW5naW5lXG4vLy8gQGF1dGhvciBIdWdvIEdpcmF1ZGVsXG4vLy8gQGFjY2VzcyBwcml2YXRlXG4vLy8vXG5cbi8vL1xuLy8vIEdldCBvcGVyYXRvciBvZiBhbiBleHByZXNzaW9uXG4vLy9cbi8vLyBAcGFyYW0ge1N0cmluZ30gJGV4cHJlc3Npb24gLSBFeHByZXNzaW9uIHRvIGV4dHJhY3Qgb3BlcmF0b3IgZnJvbVxuLy8vXG4vLy8gQHJldHVybiB7U3RyaW5nfSAtIEFueSBvZiBgPj1gLCBgPmAsIGA8PWAsIGA8YCwgYOKJpWAsIGDiiaRgXG4vLy9cbkBmdW5jdGlvbiBnZXQtZXhwcmVzc2lvbi1vcGVyYXRvcigkZXhwcmVzc2lvbikge1xuICBAZWFjaCAkb3BlcmF0b3IgaW4gKCc+PScsICc+JywgJzw9JywgJzwnLCAn4omlJywgJ+KJpCcpIHtcbiAgICBAaWYgc3RyLWluZGV4KCRleHByZXNzaW9uLCAkb3BlcmF0b3IpIHtcbiAgICAgIEByZXR1cm4gJG9wZXJhdG9yO1xuICAgIH1cbiAgfVxuXG4gIC8vIEl0IGlzIG5vdCBwb3NzaWJsZSB0byBpbmNsdWRlIGEgbWl4aW4gaW5zaWRlIGEgZnVuY3Rpb24sIHNvIHdlIGhhdmUgdG9cbiAgLy8gcmVseSBvbiB0aGUgYGltLWxvZyguLilgIGZ1bmN0aW9uIHJhdGhlciB0aGFuIHRoZSBgbG9nKC4uKWAgbWl4aW4uIEJlY2F1c2VcbiAgLy8gZnVuY3Rpb25zIGNhbm5vdCBiZSBjYWxsZWQgYW55d2hlcmUgaW4gU2Fzcywgd2UgbmVlZCB0byBoYWNrIHRoZSBjYWxsIGluXG4gIC8vIGEgZHVtbXkgdmFyaWFibGUsIHN1Y2ggYXMgYCRfYC4gSWYgYW55Ym9keSBldmVyIHJhaXNlIGEgc2NvcGluZyBpc3N1ZSB3aXRoXG4gIC8vIFNhc3MgMy4zLCBjaGFuZ2UgdGhpcyBsaW5lIGluIGBAaWYgaW0tbG9nKC4uKSB7fWAgaW5zdGVhZC5cbiAgJF86IGltLWxvZygnTm8gb3BlcmF0b3IgZm91bmQgaW4gYCN7JGV4cHJlc3Npb259YC4nKTtcbn1cblxuLy8vXG4vLy8gR2V0IGRpbWVuc2lvbiBvZiBhbiBleHByZXNzaW9uLCBiYXNlZCBvbiBhIGZvdW5kIG9wZXJhdG9yXG4vLy9cbi8vLyBAcGFyYW0ge1N0cmluZ30gJGV4cHJlc3Npb24gLSBFeHByZXNzaW9uIHRvIGV4dHJhY3QgZGltZW5zaW9uIGZyb21cbi8vLyBAcGFyYW0ge1N0cmluZ30gJG9wZXJhdG9yIC0gT3BlcmF0b3IgZnJvbSBgJGV4cHJlc3Npb25gXG4vLy9cbi8vLyBAcmV0dXJuIHtTdHJpbmd9IC0gYHdpZHRoYCBvciBgaGVpZ2h0YCAob3IgcG90ZW50aWFsbHkgYW55dGhpbmcgZWxzZSlcbi8vL1xuQGZ1bmN0aW9uIGdldC1leHByZXNzaW9uLWRpbWVuc2lvbigkZXhwcmVzc2lvbiwgJG9wZXJhdG9yKSB7XG4gICRvcGVyYXRvci1pbmRleDogc3RyLWluZGV4KCRleHByZXNzaW9uLCAkb3BlcmF0b3IpO1xuICAkcGFyc2VkLWRpbWVuc2lvbjogc3RyLXNsaWNlKCRleHByZXNzaW9uLCAwLCAkb3BlcmF0b3ItaW5kZXggLSAxKTtcbiAgJGRpbWVuc2lvbjogJ3dpZHRoJztcblxuICBAaWYgc3RyLWxlbmd0aCgkcGFyc2VkLWRpbWVuc2lvbikgPiAwIHtcbiAgICAkZGltZW5zaW9uOiAkcGFyc2VkLWRpbWVuc2lvbjtcbiAgfVxuXG4gIEByZXR1cm4gJGRpbWVuc2lvbjtcbn1cblxuLy8vXG4vLy8gR2V0IGRpbWVuc2lvbiBwcmVmaXggYmFzZWQgb24gYW4gb3BlcmF0b3Jcbi8vL1xuLy8vIEBwYXJhbSB7U3RyaW5nfSAkb3BlcmF0b3IgLSBPcGVyYXRvclxuLy8vXG4vLy8gQHJldHVybiB7U3RyaW5nfSAtIGBtaW5gIG9yIGBtYXhgXG4vLy9cbkBmdW5jdGlvbiBnZXQtZXhwcmVzc2lvbi1wcmVmaXgoJG9wZXJhdG9yKSB7XG4gIEByZXR1cm4gaWYoaW5kZXgoKCc8JywgJzw9JywgJ+KJpCcpLCAkb3BlcmF0b3IpLCAnbWF4JywgJ21pbicpO1xufVxuXG4vLy9cbi8vLyBHZXQgdmFsdWUgb2YgYW4gZXhwcmVzc2lvbiwgYmFzZWQgb24gYSBmb3VuZCBvcGVyYXRvclxuLy8vXG4vLy8gQHBhcmFtIHtTdHJpbmd9ICRleHByZXNzaW9uIC0gRXhwcmVzc2lvbiB0byBleHRyYWN0IHZhbHVlIGZyb21cbi8vLyBAcGFyYW0ge1N0cmluZ30gJG9wZXJhdG9yIC0gT3BlcmF0b3IgZnJvbSBgJGV4cHJlc3Npb25gXG4vLy9cbi8vLyBAcmV0dXJuIHtOdW1iZXJ9IC0gQSBudW1lcmljIHZhbHVlXG4vLy9cbkBmdW5jdGlvbiBnZXQtZXhwcmVzc2lvbi12YWx1ZSgkZXhwcmVzc2lvbiwgJG9wZXJhdG9yKSB7XG4gICRvcGVyYXRvci1pbmRleDogc3RyLWluZGV4KCRleHByZXNzaW9uLCAkb3BlcmF0b3IpO1xuICAkdmFsdWU6IHN0ci1zbGljZSgkZXhwcmVzc2lvbiwgJG9wZXJhdG9yLWluZGV4ICsgc3RyLWxlbmd0aCgkb3BlcmF0b3IpKTtcblxuICBAaWYgbWFwLWhhcy1rZXkoJGJyZWFrcG9pbnRzLCAkdmFsdWUpIHtcbiAgICAkdmFsdWU6IG1hcC1nZXQoJGJyZWFrcG9pbnRzLCAkdmFsdWUpO1xuICB9XG5cbiAgQGVsc2Uge1xuICAgICR2YWx1ZTogdG8tbnVtYmVyKCR2YWx1ZSk7XG4gIH1cblxuICAkaW50ZXJ2YWw6IG1hcC1nZXQoJHVuaXQtaW50ZXJ2YWxzLCB1bml0KCR2YWx1ZSkpO1xuXG4gIEBpZiBub3QgJGludGVydmFsIHtcbiAgICAvLyBJdCBpcyBub3QgcG9zc2libGUgdG8gaW5jbHVkZSBhIG1peGluIGluc2lkZSBhIGZ1bmN0aW9uLCBzbyB3ZSBoYXZlIHRvXG4gICAgLy8gcmVseSBvbiB0aGUgYGltLWxvZyguLilgIGZ1bmN0aW9uIHJhdGhlciB0aGFuIHRoZSBgbG9nKC4uKWAgbWl4aW4uIEJlY2F1c2VcbiAgICAvLyBmdW5jdGlvbnMgY2Fubm90IGJlIGNhbGxlZCBhbnl3aGVyZSBpbiBTYXNzLCB3ZSBuZWVkIHRvIGhhY2sgdGhlIGNhbGwgaW5cbiAgICAvLyBhIGR1bW15IHZhcmlhYmxlLCBzdWNoIGFzIGAkX2AuIElmIGFueWJvZHkgZXZlciByYWlzZSBhIHNjb3BpbmcgaXNzdWUgd2l0aFxuICAgIC8vIFNhc3MgMy4zLCBjaGFuZ2UgdGhpcyBsaW5lIGluIGBAaWYgaW0tbG9nKC4uKSB7fWAgaW5zdGVhZC5cbiAgICAkXzogaW0tbG9nKCdVbmtub3duIHVuaXQgYCN7dW5pdCgkdmFsdWUpfWAuJyk7XG4gIH1cblxuICBAaWYgJG9wZXJhdG9yID09ICc+JyB7XG4gICAgJHZhbHVlOiAkdmFsdWUgKyAkaW50ZXJ2YWw7XG4gIH1cblxuICBAZWxzZSBpZiAkb3BlcmF0b3IgPT0gJzwnIHtcbiAgICAkdmFsdWU6ICR2YWx1ZSAtICRpbnRlcnZhbDtcbiAgfVxuXG4gIEByZXR1cm4gJHZhbHVlO1xufVxuXG4vLy9cbi8vLyBQYXJzZSBhbiBleHByZXNzaW9uIHRvIHJldHVybiBhIHZhbGlkIG1lZGlhLXF1ZXJ5IGV4cHJlc3Npb25cbi8vL1xuLy8vIEBwYXJhbSB7U3RyaW5nfSAkZXhwcmVzc2lvbiAtIEV4cHJlc3Npb24gdG8gcGFyc2Vcbi8vL1xuLy8vIEByZXR1cm4ge1N0cmluZ30gLSBWYWxpZCBtZWRpYSBxdWVyeVxuLy8vXG5AZnVuY3Rpb24gcGFyc2UtZXhwcmVzc2lvbigkZXhwcmVzc2lvbikge1xuICAvLyBJZiBpdCBpcyBwYXJ0IG9mICRtZWRpYS1leHByZXNzaW9ucywgaXQgaGFzIG5vIG9wZXJhdG9yXG4gIC8vIHRoZW4gdGhlcmUgaXMgbm8gbmVlZCB0byBnbyBhbnkgZnVydGhlciwganVzdCByZXR1cm4gdGhlIHZhbHVlXG4gIEBpZiBtYXAtaGFzLWtleSgkbWVkaWEtZXhwcmVzc2lvbnMsICRleHByZXNzaW9uKSB7XG4gICAgQHJldHVybiBtYXAtZ2V0KCRtZWRpYS1leHByZXNzaW9ucywgJGV4cHJlc3Npb24pO1xuICB9XG5cbiAgJG9wZXJhdG9yOiBnZXQtZXhwcmVzc2lvbi1vcGVyYXRvcigkZXhwcmVzc2lvbik7XG4gICRkaW1lbnNpb246IGdldC1leHByZXNzaW9uLWRpbWVuc2lvbigkZXhwcmVzc2lvbiwgJG9wZXJhdG9yKTtcbiAgJHByZWZpeDogZ2V0LWV4cHJlc3Npb24tcHJlZml4KCRvcGVyYXRvcik7XG4gICR2YWx1ZTogZ2V0LWV4cHJlc3Npb24tdmFsdWUoJGV4cHJlc3Npb24sICRvcGVyYXRvcik7XG5cbiAgQHJldHVybiAnKCN7JHByZWZpeH0tI3skZGltZW5zaW9ufTogI3skdmFsdWV9KSc7XG59XG5cbi8vL1xuLy8vIFNsaWNlIGAkbGlzdGAgYmV0d2VlbiBgJHN0YXJ0YCBhbmQgYCRlbmRgIGluZGV4ZXNcbi8vL1xuLy8vIEBhY2Nlc3MgcHJpdmF0ZVxuLy8vXG4vLy8gQHBhcmFtIHtMaXN0fSAkbGlzdCAtIExpc3QgdG8gc2xpY2Vcbi8vLyBAcGFyYW0ge051bWJlcn0gJHN0YXJ0IFsxXSAtIFN0YXJ0IGluZGV4XG4vLy8gQHBhcmFtIHtOdW1iZXJ9ICRlbmQgW2xlbmd0aCgkbGlzdCldIC0gRW5kIGluZGV4XG4vLy9cbi8vLyBAcmV0dXJuIHtMaXN0fSBTbGljZWQgbGlzdFxuLy8vXG5AZnVuY3Rpb24gc2xpY2UoJGxpc3QsICRzdGFydDogMSwgJGVuZDogbGVuZ3RoKCRsaXN0KSkge1xuICBAaWYgbGVuZ3RoKCRsaXN0KSA8IDEgb3IgJHN0YXJ0ID4gJGVuZCB7XG4gICAgQHJldHVybiAoKTtcbiAgfVxuXG4gICRyZXN1bHQ6ICgpO1xuXG4gIEBmb3IgJGkgZnJvbSAkc3RhcnQgdGhyb3VnaCAkZW5kIHtcbiAgICAkcmVzdWx0OiBhcHBlbmQoJHJlc3VsdCwgbnRoKCRsaXN0LCAkaSkpO1xuICB9XG5cbiAgQHJldHVybiAkcmVzdWx0O1xufVxuXG4vLy8vXG4vLy8gU3RyaW5nIHRvIG51bWJlciBjb252ZXJ0ZXJcbi8vLyBAYXV0aG9yIEh1Z28gR2lyYXVkZWxcbi8vLyBAYWNjZXNzIHByaXZhdGVcbi8vLy9cblxuLy8vXG4vLy8gQ2FzdHMgYSBzdHJpbmcgaW50byBhIG51bWJlclxuLy8vXG4vLy8gQHBhcmFtIHtTdHJpbmcgfCBOdW1iZXJ9ICR2YWx1ZSAtIFZhbHVlIHRvIGJlIHBhcnNlZFxuLy8vXG4vLy8gQHJldHVybiB7TnVtYmVyfVxuLy8vXG5AZnVuY3Rpb24gdG8tbnVtYmVyKCR2YWx1ZSkge1xuICBAaWYgdHlwZS1vZigkdmFsdWUpID09ICdudW1iZXInIHtcbiAgICBAcmV0dXJuICR2YWx1ZTtcbiAgfVxuXG4gIEBlbHNlIGlmIHR5cGUtb2YoJHZhbHVlKSAhPSAnc3RyaW5nJyB7XG4gICAgJF86IGltLWxvZygnVmFsdWUgZm9yIGB0by1udW1iZXJgIHNob3VsZCBiZSBhIG51bWJlciBvciBhIHN0cmluZy4nKTtcbiAgfVxuXG4gICRmaXJzdC1jaGFyYWN0ZXI6IHN0ci1zbGljZSgkdmFsdWUsIDEsIDEpO1xuICAkcmVzdWx0OiAwO1xuICAkZGlnaXRzOiAwO1xuICAkbWludXM6ICgkZmlyc3QtY2hhcmFjdGVyID09ICctJyk7XG4gICRudW1iZXJzOiAoJzAnOiAwLCAnMSc6IDEsICcyJzogMiwgJzMnOiAzLCAnNCc6IDQsICc1JzogNSwgJzYnOiA2LCAnNyc6IDcsICc4JzogOCwgJzknOiA5KTtcblxuICAvLyBSZW1vdmUgKy8tIHNpZ24gaWYgcHJlc2VudCBhdCBmaXJzdCBjaGFyYWN0ZXJcbiAgQGlmICgkZmlyc3QtY2hhcmFjdGVyID09ICcrJyBvciAkZmlyc3QtY2hhcmFjdGVyID09ICctJykge1xuICAgICR2YWx1ZTogc3RyLXNsaWNlKCR2YWx1ZSwgMik7XG4gIH1cblxuICBAZm9yICRpIGZyb20gMSB0aHJvdWdoIHN0ci1sZW5ndGgoJHZhbHVlKSB7XG4gICAgJGNoYXJhY3Rlcjogc3RyLXNsaWNlKCR2YWx1ZSwgJGksICRpKTtcblxuICAgIEBpZiBub3QgKGluZGV4KG1hcC1rZXlzKCRudW1iZXJzKSwgJGNoYXJhY3Rlcikgb3IgJGNoYXJhY3RlciA9PSAnLicpIHtcbiAgICAgIEByZXR1cm4gdG8tbGVuZ3RoKGlmKCRtaW51cywgLSRyZXN1bHQsICRyZXN1bHQpLCBzdHItc2xpY2UoJHZhbHVlLCAkaSkpO1xuICAgIH1cblxuICAgIEBpZiAkY2hhcmFjdGVyID09ICcuJyB7XG4gICAgICAkZGlnaXRzOiAxO1xuICAgIH1cblxuICAgIEBlbHNlIGlmICRkaWdpdHMgPT0gMCB7XG4gICAgICAkcmVzdWx0OiAkcmVzdWx0ICogMTAgKyBtYXAtZ2V0KCRudW1iZXJzLCAkY2hhcmFjdGVyKTtcbiAgICB9XG5cbiAgICBAZWxzZSB7XG4gICAgICAkZGlnaXRzOiAkZGlnaXRzICogMTA7XG4gICAgICAkcmVzdWx0OiAkcmVzdWx0ICsgbWFwLWdldCgkbnVtYmVycywgJGNoYXJhY3RlcikgLyAkZGlnaXRzO1xuICAgIH1cbiAgfVxuXG4gIEByZXR1cm4gaWYoJG1pbnVzLCAtJHJlc3VsdCwgJHJlc3VsdCk7XG59XG5cbi8vL1xuLy8vIEFkZCBgJHVuaXRgIHRvIGAkdmFsdWVgXG4vLy9cbi8vLyBAcGFyYW0ge051bWJlcn0gJHZhbHVlIC0gVmFsdWUgdG8gYWRkIHVuaXQgdG9cbi8vLyBAcGFyYW0ge1N0cmluZ30gJHVuaXQgLSBTdHJpbmcgcmVwcmVzZW50YXRpb24gb2YgdGhlIHVuaXRcbi8vL1xuLy8vIEByZXR1cm4ge051bWJlcn0gLSBgJHZhbHVlYCBleHByZXNzZWQgaW4gYCR1bml0YFxuLy8vXG5AZnVuY3Rpb24gdG8tbGVuZ3RoKCR2YWx1ZSwgJHVuaXQpIHtcbiAgJHVuaXRzOiAoJ3B4JzogMXB4LCAnY20nOiAxY20sICdtbSc6IDFtbSwgJyUnOiAxJSwgJ2NoJzogMWNoLCAncGMnOiAxcGMsICdpbic6IDFpbiwgJ2VtJzogMWVtLCAncmVtJzogMXJlbSwgJ3B0JzogMXB0LCAnZXgnOiAxZXgsICd2dyc6IDF2dywgJ3ZoJzogMXZoLCAndm1pbic6IDF2bWluLCAndm1heCc6IDF2bWF4KTtcblxuICBAaWYgbm90IGluZGV4KG1hcC1rZXlzKCR1bml0cyksICR1bml0KSB7XG4gICAgJF86IGltLWxvZygnSW52YWxpZCB1bml0IGAjeyR1bml0fWAuJyk7XG4gIH1cblxuICBAcmV0dXJuICR2YWx1ZSAqIG1hcC1nZXQoJHVuaXRzLCAkdW5pdCk7XG59XG5cbi8vL1xuLy8vIFRoaXMgbWl4aW4gYWltcyBhdCByZWRlZmluaW5nIHRoZSBjb25maWd1cmF0aW9uIGp1c3QgZm9yIHRoZSBzY29wZSBvZlxuLy8vIHRoZSBjYWxsLiBJdCBpcyBoZWxwZnVsIHdoZW4gaGF2aW5nIGEgY29tcG9uZW50IG5lZWRpbmcgYW4gZXh0ZW5kZWRcbi8vLyBjb25maWd1cmF0aW9uIHN1Y2ggYXMgY3VzdG9tIGJyZWFrcG9pbnRzIChyZWZlcnJlZCB0byBhcyB0d2Vha3BvaW50cylcbi8vLyBmb3IgaW5zdGFuY2UuXG4vLy9cbi8vLyBAYXV0aG9yIEh1Z28gR2lyYXVkZWxcbi8vL1xuLy8vIEBwYXJhbSB7TWFwfSAkdHdlYWtwb2ludHMgWygpXSAtIE1hcCBvZiB0d2Vha3BvaW50cyB0byBiZSBtZXJnZWQgd2l0aCBgJGJyZWFrcG9pbnRzYFxuLy8vIEBwYXJhbSB7TWFwfSAkdHdlYWstbWVkaWEtZXhwcmVzc2lvbnMgWygpXSAtIE1hcCBvZiB0d2Vha2VkIG1lZGlhIGV4cHJlc3Npb25zIHRvIGJlIG1lcmdlZCB3aXRoIGAkbWVkaWEtZXhwcmVzc2lvbmBcbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBFeHRlbmQgdGhlIGdsb2JhbCBicmVha3BvaW50cyB3aXRoIGEgdHdlYWtwb2ludFxuLy8vICBAaW5jbHVkZSBtZWRpYS1jb250ZXh0KCgnY3VzdG9tJzogNjc4cHgpKSB7XG4vLy8gICAgLmZvbyB7XG4vLy8gICAgICBAaW5jbHVkZSBtZWRpYSgnPnBob25lJywgJzw9Y3VzdG9tJykge1xuLy8vICAgICAgIC8vIC4uLlxuLy8vICAgICAgfVxuLy8vICAgIH1cbi8vLyAgfVxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIEV4dGVuZCB0aGUgZ2xvYmFsIG1lZGlhIGV4cHJlc3Npb25zIHdpdGggYSBjdXN0b20gb25lXG4vLy8gIEBpbmNsdWRlIG1lZGlhLWNvbnRleHQoJHR3ZWFrLW1lZGlhLWV4cHJlc3Npb25zOiAoJ2FsbCc6ICdhbGwnKSkge1xuLy8vICAgIC5mb28ge1xuLy8vICAgICAgQGluY2x1ZGUgbWVkaWEoJ2FsbCcsICc+cGhvbmUnKSB7XG4vLy8gICAgICAgLy8gLi4uXG4vLy8gICAgICB9XG4vLy8gICAgfVxuLy8vICB9XG4vLy9cbi8vLyBAZXhhbXBsZSBzY3NzIC0gRXh0ZW5kIGJvdGggY29uZmlndXJhdGlvbiBtYXBzXG4vLy8gIEBpbmNsdWRlIG1lZGlhLWNvbnRleHQoKCdjdXN0b20nOiA2NzhweCksICgnYWxsJzogJ2FsbCcpKSB7XG4vLy8gICAgLmZvbyB7XG4vLy8gICAgICBAaW5jbHVkZSBtZWRpYSgnYWxsJywgJz5waG9uZScsICc8PWN1c3RvbScpIHtcbi8vLyAgICAgICAvLyAuLi5cbi8vLyAgICAgIH1cbi8vLyAgICB9XG4vLy8gIH1cbi8vL1xuQG1peGluIG1lZGlhLWNvbnRleHQoJHR3ZWFrcG9pbnRzOiAoKSwgJHR3ZWFrLW1lZGlhLWV4cHJlc3Npb25zOiAoKSkge1xuICAvLyBTYXZlIGdsb2JhbCBjb25maWd1cmF0aW9uXG4gICRnbG9iYWwtYnJlYWtwb2ludHM6ICRicmVha3BvaW50cztcbiAgJGdsb2JhbC1tZWRpYS1leHByZXNzaW9uczogJG1lZGlhLWV4cHJlc3Npb25zO1xuXG4gIC8vIFVwZGF0ZSBnbG9iYWwgY29uZmlndXJhdGlvblxuICAkYnJlYWtwb2ludHM6IG1hcC1tZXJnZSgkYnJlYWtwb2ludHMsICR0d2Vha3BvaW50cykgIWdsb2JhbDtcbiAgJG1lZGlhLWV4cHJlc3Npb25zOiBtYXAtbWVyZ2UoJG1lZGlhLWV4cHJlc3Npb25zLCAkdHdlYWstbWVkaWEtZXhwcmVzc2lvbnMpICFnbG9iYWw7XG5cbiAgQGNvbnRlbnQ7XG5cbiAgLy8gUmVzdG9yZSBnbG9iYWwgY29uZmlndXJhdGlvblxuICAkYnJlYWtwb2ludHM6ICRnbG9iYWwtYnJlYWtwb2ludHMgIWdsb2JhbDtcbiAgJG1lZGlhLWV4cHJlc3Npb25zOiAkZ2xvYmFsLW1lZGlhLWV4cHJlc3Npb25zICFnbG9iYWw7XG59XG5cbi8vLy9cbi8vLyBpbmNsdWRlLW1lZGlhIHB1YmxpYyBleHBvc2VkIEFQSVxuLy8vIEBhdXRob3IgRWR1YXJkbyBCb3VjYXNcbi8vLyBAYWNjZXNzIHB1YmxpY1xuLy8vL1xuXG4vLy9cbi8vLyBHZW5lcmF0ZXMgYSBtZWRpYSBxdWVyeSBiYXNlZCBvbiBhIGxpc3Qgb2YgY29uZGl0aW9uc1xuLy8vXG4vLy8gQHBhcmFtIHtBcmdsaXN0fSAgICRjb25kaXRpb25zICAtIE1lZGlhIHF1ZXJ5IGNvbmRpdGlvbnNcbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBXaXRoIGEgc2luZ2xlIHNldCBicmVha3BvaW50XG4vLy8gIEBpbmNsdWRlIG1lZGlhKCc+cGhvbmUnKSB7IH1cbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBXaXRoIHR3byBzZXQgYnJlYWtwb2ludHNcbi8vLyAgQGluY2x1ZGUgbWVkaWEoJz5waG9uZScsICc8PXRhYmxldCcpIHsgfVxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIFdpdGggY3VzdG9tIHZhbHVlc1xuLy8vICBAaW5jbHVkZSBtZWRpYSgnPj0zNThweCcsICc8ODUwcHgnKSB7IH1cbi8vL1xuLy8vIEBleGFtcGxlIHNjc3MgLSBXaXRoIHNldCBicmVha3BvaW50cyB3aXRoIGN1c3RvbSB2YWx1ZXNcbi8vLyAgQGluY2x1ZGUgbWVkaWEoJz5kZXNrdG9wJywgJzw9MTM1MHB4JykgeyB9XG4vLy9cbi8vLyBAZXhhbXBsZSBzY3NzIC0gV2l0aCBhIHN0YXRpYyBleHByZXNzaW9uXG4vLy8gIEBpbmNsdWRlIG1lZGlhKCdyZXRpbmEyeCcpIHsgfVxuLy8vXG4vLy8gQGV4YW1wbGUgc2NzcyAtIE1peGluZyBldmVyeXRoaW5nXG4vLy8gIEBpbmNsdWRlIG1lZGlhKCc+PTM1MHB4JywgJzx0YWJsZXQnLCAncmV0aW5hM3gnKSB7IH1cbi8vL1xuQG1peGluIG1lZGlhKCRjb25kaXRpb25zLi4uKSB7XG4gIEBpZiAoJGltLW1lZGlhLXN1cHBvcnQgYW5kIGxlbmd0aCgkY29uZGl0aW9ucykgPT0gMCkgb3IgKG5vdCAkaW0tbWVkaWEtc3VwcG9ydCBhbmQgaW0taW50ZXJjZXB0cy1zdGF0aWMtYnJlYWtwb2ludCgkY29uZGl0aW9ucy4uLikpIHtcbiAgICBAY29udGVudDtcbiAgfVxuXG4gIEBlbHNlIGlmICgkaW0tbWVkaWEtc3VwcG9ydCBhbmQgbGVuZ3RoKCRjb25kaXRpb25zKSA+IDApIHtcbiAgICBAbWVkaWEgI3t1bnF1b3RlKHBhcnNlLWV4cHJlc3Npb24obnRoKCRjb25kaXRpb25zLCAxKSkpfSB7XG5cbiAgICAgIC8vIFJlY3Vyc2l2ZSBjYWxsXG4gICAgICBAaW5jbHVkZSBtZWRpYShzbGljZSgkY29uZGl0aW9ucywgMikuLi4pIHtcbiAgICAgICAgQGNvbnRlbnQ7XG4gICAgICB9XG4gICAgfVxuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJE1FRElBIFFVRVJZIFRFU1RTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbkBpZiAkdGVzdHMgPT0gdHJ1ZSB7XG4gIGJvZHkge1xuICAgICY6OmJlZm9yZSB7XG4gICAgICBkaXNwbGF5OiBibG9jaztcbiAgICAgIHBvc2l0aW9uOiBmaXhlZDtcbiAgICAgIHotaW5kZXg6ICR6LWluZGV4LW1xLWRpc3BsYXk7XG4gICAgICBiYWNrZ3JvdW5kOiBibGFjaztcbiAgICAgIGJvdHRvbTogMDtcbiAgICAgIHJpZ2h0OiAwO1xuICAgICAgcGFkZGluZzogMC41ZW0gMWVtO1xuICAgICAgY29udGVudDogJ05vIE1lZGlhIFF1ZXJ5JztcbiAgICAgIGNvbG9yOiB0cmFuc3BhcmVudGl6ZSgjZmZmLCAwLjI1KTtcbiAgICAgIGJvcmRlci10b3AtbGVmdC1yYWRpdXM6IDEwcHg7XG4gICAgICBmb250LXNpemU6IDEyIC8gMTYgKyBlbTtcblxuICAgICAgQG1lZGlhIHByaW50IHtcbiAgICAgICAgZGlzcGxheTogbm9uZTtcbiAgICAgIH1cbiAgICB9XG5cbiAgICAmOjphZnRlciB7XG4gICAgICBkaXNwbGF5OiBibG9jaztcbiAgICAgIHBvc2l0aW9uOiBmaXhlZDtcbiAgICAgIGhlaWdodDogNXB4O1xuICAgICAgYm90dG9tOiAwO1xuICAgICAgbGVmdDogMDtcbiAgICAgIHJpZ2h0OiAwO1xuICAgICAgei1pbmRleDogJHotaW5kZXgtbXEtZGlzcGxheTtcbiAgICAgIGNvbnRlbnQ6ICcnO1xuICAgICAgYmFja2dyb3VuZDogYmxhY2s7XG5cbiAgICAgIEBtZWRpYSBwcmludCB7XG4gICAgICAgIGRpc3BsYXk6IG5vbmU7XG4gICAgICB9XG4gICAgfVxuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+eHNtYWxsXCIpIHtcbiAgICAgICY6OmJlZm9yZSB7XG4gICAgICAgIGNvbnRlbnQ6IFwieHNtYWxsOiAjeyR4c21hbGx9XCI7XG4gICAgICB9XG5cbiAgICAgICY6OmFmdGVyLFxuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgYmFja2dyb3VuZDogZG9kZ2VyYmx1ZTtcbiAgICAgIH1cbiAgICB9XG5cblxuICAgIEBpbmNsdWRlIG1lZGlhKFwiPnNtYWxsXCIpIHtcbiAgICAgICY6OmJlZm9yZSB7XG4gICAgICAgIGNvbnRlbnQ6IFwic21hbGw6ICN7JHNtYWxsfVwiO1xuICAgICAgfVxuXG4gICAgICAmOjphZnRlcixcbiAgICAgICY6OmJlZm9yZSB7XG4gICAgICAgIGJhY2tncm91bmQ6IGRhcmtzZWFncmVlbjtcbiAgICAgIH1cbiAgICB9XG5cblxuICAgIEBpbmNsdWRlIG1lZGlhKFwiPm1lZGl1bVwiKSB7XG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBjb250ZW50OiBcIm1lZGl1bTogI3skbWVkaXVtfVwiO1xuICAgICAgfVxuXG4gICAgICAmOjphZnRlcixcbiAgICAgICY6OmJlZm9yZSB7XG4gICAgICAgIGJhY2tncm91bmQ6IGxpZ2h0Y29yYWw7XG4gICAgICB9XG4gICAgfVxuXG5cbiAgICBAaW5jbHVkZSBtZWRpYShcIj5sYXJnZVwiKSB7XG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBjb250ZW50OiBcImxhcmdlOiAjeyRsYXJnZX1cIjtcbiAgICAgIH1cblxuICAgICAgJjo6YWZ0ZXIsXG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBiYWNrZ3JvdW5kOiBtZWRpdW12aW9sZXRyZWQ7XG4gICAgICB9XG4gICAgfVxuXG5cbiAgICBAaW5jbHVkZSBtZWRpYShcIj54bGFyZ2VcIikge1xuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgY29udGVudDogXCJ4bGFyZ2U6ICN7JHhsYXJnZX1cIjtcbiAgICAgIH1cblxuICAgICAgJjo6YWZ0ZXIsXG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBiYWNrZ3JvdW5kOiBob3RwaW5rO1xuICAgICAgfVxuICAgIH1cblxuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+eHhsYXJnZVwiKSB7XG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBjb250ZW50OiBcInh4bGFyZ2U6ICN7JHh4bGFyZ2V9XCI7XG4gICAgICB9XG5cbiAgICAgICY6OmFmdGVyLFxuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgYmFja2dyb3VuZDogb3JhbmdlcmVkO1xuICAgICAgfVxuICAgIH1cblxuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+eHh4bGFyZ2VcIikge1xuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgY29udGVudDogXCJ4eHhsYXJnZTogI3skeHh4bGFyZ2V9XCI7XG4gICAgICB9XG5cbiAgICAgICY6OmFmdGVyLFxuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgYmFja2dyb3VuZDogZG9kZ2VyYmx1ZTtcbiAgICAgIH1cbiAgICB9XG4gIH1cbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkUkVTRVRcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLyogQm9yZGVyLUJveCBodHRwOi9wYXVsaXJpc2guY29tLzIwMTIvYm94LXNpemluZy1ib3JkZXItYm94LWZ0dy8gKi9cbiosXG4qOjpiZWZvcmUsXG4qOjphZnRlciB7XG4gIGJveC1zaXppbmc6IGJvcmRlci1ib3g7XG59XG5cbmJvZHkge1xuICBtYXJnaW46IDA7XG4gIHBhZGRpbmc6IDA7XG59XG5cbmJsb2NrcXVvdGUsXG5ib2R5LFxuZGl2LFxuZmlndXJlLFxuZm9vdGVyLFxuZm9ybSxcbmgxLFxuaDIsXG5oMyxcbmg0LFxuaDUsXG5oNixcbmhlYWRlcixcbmh0bWwsXG5pZnJhbWUsXG5sYWJlbCxcbmxlZ2VuZCxcbmxpLFxubmF2LFxub2JqZWN0LFxub2wsXG5wLFxuc2VjdGlvbixcbnRhYmxlLFxudWwge1xuICBtYXJnaW46IDA7XG4gIHBhZGRpbmc6IDA7XG59XG5cbmFydGljbGUsXG5maWd1cmUsXG5mb290ZXIsXG5oZWFkZXIsXG5oZ3JvdXAsXG5uYXYsXG5zZWN0aW9uIHtcbiAgZGlzcGxheTogYmxvY2s7XG59XG5cbmFkZHJlc3Mge1xuICBmb250LXN0eWxlOiBub3JtYWw7XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJEZPTlRTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5AaW1wb3J0IHVybChcImh0dHBzOi8vZm9udHMuZ29vZ2xlYXBpcy5jb20vY3NzMj9mYW1pbHk9Um9ib3RvOml0YWwsd2dodEAwLDQwMDswLDcwMDsxLDQwMDsxLDcwMCZkaXNwbGF5PXN3YXBcIik7XG5cbkBmb250LWZhY2Uge1xuICBmb250LWZhbWlseTogJ0JpZyBKb2huJztcbiAgc3JjOiB1cmwoXCIuLi9mb250cy9iaWdfam9obi13ZWJmb250LndvZmYyXCIpIGZvcm1hdChcIndvZmYyXCIpLCB1cmwoXCIuLi9mb250cy9iaWdfam9obi13ZWJmb250LndvZmZcIikgZm9ybWF0KFwid29mZlwiKTtcbiAgZm9udC13ZWlnaHQ6IG5vcm1hbDtcbiAgZm9udC1zdHlsZTogbm9ybWFsO1xufVxuXG5AZm9udC1mYWNlIHtcbiAgZm9udC1mYW1pbHk6ICdHbyBCb2xkJztcbiAgc3JjOiB1cmwoXCIuLi9mb250cy9nb2JvbGRfcmVndWxhcl9pdGFsaWMtd2ViZm9udC53b2ZmMlwiKSBmb3JtYXQoXCJ3b2ZmMlwiKSwgdXJsKFwiLi4vZm9udHMvZ29ib2xkX3JlZ3VsYXJfaXRhbGljLXdlYmZvbnQud29mZlwiKSBmb3JtYXQoXCJ3b2ZmXCIpO1xuICBmb250LXdlaWdodDogbm9ybWFsO1xuICBmb250LXN0eWxlOiBub3JtYWw7XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJEZPUk1TXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbmZvcm0gb2wsXG5mb3JtIHVsIHtcbiAgbGlzdC1zdHlsZTogbm9uZTtcbiAgbWFyZ2luLWxlZnQ6IDA7XG59XG5cbmxlZ2VuZCB7XG4gIG1hcmdpbi1ib3R0b206IDZweDtcbiAgZm9udC13ZWlnaHQ6IGJvbGQ7XG59XG5cbmZpZWxkc2V0IHtcbiAgYm9yZGVyOiAwO1xuICBwYWRkaW5nOiAwO1xuICBtYXJnaW46IDA7XG4gIG1pbi13aWR0aDogMDtcbn1cblxuaW5wdXQsXG5zZWxlY3QsXG50ZXh0YXJlYSB7XG4gIHdpZHRoOiAxMDAlO1xuICBib3JkZXI6IG5vbmU7XG4gIGFwcGVhcmFuY2U6IG5vbmU7XG59XG5cbmlucHV0W3R5cGU9dGV4dF0sXG5pbnB1dFt0eXBlPXBhc3N3b3JkXSxcbmlucHV0W3R5cGU9ZW1haWxdLFxuaW5wdXRbdHlwZT1zZWFyY2hdLFxuaW5wdXRbdHlwZT10ZWxdLFxuc2VsZWN0IHtcbiAgZm9udC1zaXplOiAxNnB4O1xuICBmb250LWZhbWlseTogJGZmLWZvbnQ7XG4gIHBhZGRpbmc6IDE4cHggJHNwYWNlO1xuICBib3gtc2hhZG93OiBub25lO1xuICBib3JkZXI6ICRib3JkZXItLXN0YW5kYXJkO1xuXG4gICY6OnBsYWNlaG9sZGVyIHtcbiAgICBjb2xvcjogJGMtZ3JheTtcbiAgfVxuXG4gICY6Zm9jdXMge1xuICAgIG91dGxpbmU6IDJweCBzb2xpZCAkYy1ib3JkZXI7XG4gIH1cbn1cblxuaW5wdXRbdHlwZT1yYWRpb10sXG5pbnB1dFt0eXBlPWNoZWNrYm94XSB7XG4gIG91dGxpbmU6IG5vbmU7XG4gIG1hcmdpbjogMDtcbiAgbWFyZ2luLXJpZ2h0OiAkc3BhY2UtcXVhcnRlcjtcbiAgaGVpZ2h0OiAxOHB4O1xuICB3aWR0aDogMThweDtcbiAgbGluZS1oZWlnaHQ6IDE7XG4gIGJhY2tncm91bmQtc2l6ZTogMThweDtcbiAgYmFja2dyb3VuZC1yZXBlYXQ6IG5vLXJlcGVhdDtcbiAgYmFja2dyb3VuZC1wb3NpdGlvbjogMCAwO1xuICBjdXJzb3I6IHBvaW50ZXI7XG4gIGRpc3BsYXk6IGJsb2NrO1xuICBmbG9hdDogbGVmdDtcbiAgYm9yZGVyOiAkYm9yZGVyLS1zdGFuZGFyZDtcbiAgcGFkZGluZzogMDtcbiAgdXNlci1zZWxlY3Q6IG5vbmU7XG4gIGFwcGVhcmFuY2U6IG5vbmU7XG4gIGJhY2tncm91bmQtY29sb3I6ICRjLXdoaXRlO1xufVxuXG5pbnB1dFt0eXBlPXJhZGlvXSArIGxhYmVsLFxuaW5wdXRbdHlwZT1jaGVja2JveF0gKyBsYWJlbCB7XG4gIGRpc3BsYXk6IGlubGluZS1ibG9jaztcbiAgY3Vyc29yOiBwb2ludGVyO1xuICBwb3NpdGlvbjogcmVsYXRpdmU7XG4gIG1hcmdpbi1ib3R0b206IDA7XG59XG5cbmlucHV0W3R5cGU9cmFkaW9dOmNoZWNrZWQsXG5pbnB1dFt0eXBlPWNoZWNrYm94XTpjaGVja2VkIHtcbiAgYmFja2dyb3VuZC1pbWFnZTogdXJsKFwiZGF0YTppbWFnZS9zdmcreG1sLCUzQ3N2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHZpZXdCb3g9JzAgMCAzMCAzMCclM0UlM0NwYXRoIGQ9J00yNi4wOCwzLjU2bC0yLDEuOTVMMTAuNjEsMTlsLTUtNEwzLjQ3LDEzLjI5LDAsMTcuNjJsMi4xNywxLjczTDkuMSwyNC45LDExLDI2LjQ0bDEuNzctMS43NkwyOC4wNSw5LjQzLDMwLDcuNDhaJyBmaWxsPScje3VybC1mcmllbmRseS1jb2xvcigkYy1zZWNvbmRhcnkpfScvJTNFJTNDL3N2ZyUzRVwiKTtcbiAgYmFja2dyb3VuZC1yZXBlYXQ6IG5vLXJlcGVhdDtcbiAgYmFja2dyb3VuZC1wb3NpdGlvbjogY2VudGVyIGNlbnRlcjtcbiAgYmFja2dyb3VuZC1zaXplOiAxMHB4O1xufVxuXG5pbnB1dFt0eXBlPXJhZGlvXSB7XG4gIGJvcmRlci1yYWRpdXM6IDUwcHg7XG59XG5cbmlucHV0W3R5cGU9Y2hlY2tib3hdIHtcbiAgYm9yZGVyLXJhZGl1czogJGJvcmRlci1yYWRpdXMtY3VydmU7XG59XG5cbmlucHV0W3R5cGU9c3VibWl0XSB7XG4gIHRyYW5zaXRpb246ICR0cmFuc2l0aW9uLWFsbDtcbn1cblxuLyogY2xlYXJzIHRoZSAnWCcgZnJvbSBJbnRlcm5ldCBFeHBsb3JlciAqL1xuaW5wdXRbdHlwZT1zZWFyY2hdOjotbXMtY2xlYXIge1xuICBkaXNwbGF5OiBub25lO1xuICB3aWR0aDogMDtcbiAgaGVpZ2h0OiAwO1xufVxuXG5pbnB1dFt0eXBlPXNlYXJjaF06Oi1tcy1yZXZlYWwge1xuICBkaXNwbGF5OiBub25lO1xuICB3aWR0aDogMDtcbiAgaGVpZ2h0OiAwO1xufVxuXG4vKiBjbGVhcnMgdGhlICdYJyBmcm9tIENocm9tZSAqL1xuaW5wdXRbdHlwZT1cInNlYXJjaFwiXTo6LXdlYmtpdC1zZWFyY2gtZGVjb3JhdGlvbixcbmlucHV0W3R5cGU9XCJzZWFyY2hcIl06Oi13ZWJraXQtc2VhcmNoLWNhbmNlbC1idXR0b24sXG5pbnB1dFt0eXBlPVwic2VhcmNoXCJdOjotd2Via2l0LXNlYXJjaC1yZXN1bHRzLWJ1dHRvbixcbmlucHV0W3R5cGU9XCJzZWFyY2hcIl06Oi13ZWJraXQtc2VhcmNoLXJlc3VsdHMtZGVjb3JhdGlvbiB7XG4gIGRpc3BsYXk6IG5vbmU7XG59XG5cbi8qIHJlbW92ZXMgdGhlIGJsdWUgYmFja2dyb3VuZCBvbiBDaHJvbWUncyBhdXRvY29tcGxldGUgKi9cbmlucHV0Oi13ZWJraXQtYXV0b2ZpbGwsXG5pbnB1dDotd2Via2l0LWF1dG9maWxsOmhvdmVyLFxuaW5wdXQ6LXdlYmtpdC1hdXRvZmlsbDpmb2N1cyxcbmlucHV0Oi13ZWJraXQtYXV0b2ZpbGw6YWN0aXZlIHtcbiAgLXdlYmtpdC1ib3gtc2hhZG93OiAwIDAgMCAzMHB4IHdoaXRlIGluc2V0O1xufVxuXG5zZWxlY3Qge1xuICBiYWNrZ3JvdW5kLWNvbG9yOiAkYy13aGl0ZTtcbiAgYXBwZWFyYW5jZTogbm9uZTtcbiAgcG9zaXRpb246IHJlbGF0aXZlO1xuICB3aWR0aDogMTAwJTtcbiAgcGFkZGluZy1yaWdodDogJHNwYWNlLWFuZC1oYWxmO1xufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRIRUFESU5HU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG5AbWl4aW4gby1oZWFkaW5nLS14eGwge1xuICBmb250LWZhbWlseTogJGZmLWZvbnQtLXByaW1hcnk7XG4gIGZvbnQtc2l6ZTogJGZvbnQtc2l6ZS14eGw7XG4gIGxpbmUtaGVpZ2h0OiAxLjM7XG59XG5cbmgxLFxuLm8taGVhZGluZy0teHhsIHtcbiAgQGluY2x1ZGUgby1oZWFkaW5nLS14eGw7XG59XG5cbkBtaXhpbiBvLWhlYWRpbmctLXhsIHtcbiAgZm9udC1mYW1pbHk6ICRmZi1mb250LS1wcmltYXJ5O1xuICBmb250LXNpemU6ICRmb250LXNpemUteGw7XG4gIGxpbmUtaGVpZ2h0OiAxLjM7XG59XG5cbmgyLFxuLm8taGVhZGluZy0teGwge1xuICBAaW5jbHVkZSBvLWhlYWRpbmctLXhsO1xufVxuXG5AbWl4aW4gby1oZWFkaW5nLS1sIHtcbiAgZm9udC1mYW1pbHk6ICRmZi1mb250LS1wcmltYXJ5O1xuICBmb250LXNpemU6ICRmb250LXNpemUtbDtcbiAgbGluZS1oZWlnaHQ6IDEuNDtcbn1cblxuaDMsXG4uby1oZWFkaW5nLS1sIHtcbiAgQGluY2x1ZGUgby1oZWFkaW5nLS1sO1xufVxuXG5AbWl4aW4gby1oZWFkaW5nLS1tIHtcbiAgZm9udC1mYW1pbHk6ICRmZi1mb250LS1wcmltYXJ5O1xuICBmb250LXNpemU6ICRmb250LXNpemUtbTtcbiAgbGluZS1oZWlnaHQ6IDEuNDtcbn1cblxuaDQsXG4uby1oZWFkaW5nLS1tIHtcbiAgQGluY2x1ZGUgby1oZWFkaW5nLS1tO1xufVxuXG5AbWl4aW4gby1oZWFkaW5nLS1zIHtcbiAgZm9udC1mYW1pbHk6ICRmZi1mb250LS1wcmltYXJ5O1xuICBmb250LXNpemU6ICRmb250LXNpemUtcztcbiAgbGluZS1oZWlnaHQ6IDEuNjtcbn1cblxuaDUsXG4uby1oZWFkaW5nLS1zIHtcbiAgQGluY2x1ZGUgby1oZWFkaW5nLS1zO1xufVxuXG5AbWl4aW4gby1oZWFkaW5nLS14cyB7XG4gIGZvbnQtZmFtaWx5OiAkZmYtZm9udC0tcHJpbWFyeTtcbiAgZm9udC1zaXplOiAkZm9udC1zaXplLXhzO1xuICBsaW5lLWhlaWdodDogMS41O1xufVxuXG5oNixcbi5vLWhlYWRpbmctLXhzIHtcbiAgQGluY2x1ZGUgby1oZWFkaW5nLS14cztcbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkTElOS1NcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuYSB7XG4gIHRleHQtZGVjb3JhdGlvbjogbm9uZTtcbiAgY29sb3I6ICRjLWxpbmstY29sb3I7XG4gIHRyYW5zaXRpb246ICR0cmFuc2l0aW9uLWFsbDtcblxuICAmOmhvdmVyIHtcbiAgICBjb2xvcjogJGMtbGluay1ob3Zlci1jb2xvcjtcbiAgfVxufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRMSVNUU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG5vbCxcbnVsIHtcbiAgbWFyZ2luOiAwO1xuICBwYWRkaW5nOiAwO1xuICBsaXN0LXN0eWxlOiBub25lO1xufVxuXG4vKipcbiAqIERlZmluaXRpb24gTGlzdHNcbiAqL1xuZGwge1xuICBvdmVyZmxvdzogaGlkZGVuO1xuICBtYXJnaW46IDAgMCAkc3BhY2U7XG59XG5cbmR0IHtcbiAgZm9udC13ZWlnaHQ6IGJvbGQ7XG59XG5cbmRkIHtcbiAgbWFyZ2luLWxlZnQ6IDA7XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJFNJVEUgTUFJTlxuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG5ib2R5IHtcbiAgYmFja2dyb3VuZDogJGMtd2hpdGU7XG4gIGZvbnQ6IDQwMCAxNnB4IC8gMS4zICRmZi1mb250O1xuICAtd2Via2l0LXRleHQtc2l6ZS1hZGp1c3Q6IDEwMCU7XG4gIGNvbG9yOiAkYy1ib2R5LWNvbG9yO1xuICAtd2Via2l0LWZvbnQtc21vb3RoaW5nOiBhbnRpYWxpYXNlZDtcbiAgLW1vei1vc3gtZm9udC1zbW9vdGhpbmc6IGdyYXlzY2FsZTtcbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkTUVESUEgRUxFTUVOVFNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLyoqXG4gKiBGbGV4aWJsZSBNZWRpYVxuICovXG5pbWcsXG52aWRlbyxcbm9iamVjdCxcbnN2ZyxcbmlmcmFtZSB7XG4gIG1heC13aWR0aDogMTAwJTtcbiAgYm9yZGVyOiBub25lO1xuICBkaXNwbGF5OiBibG9jaztcbn1cblxuaW1nIHtcbiAgaGVpZ2h0OiBhdXRvO1xufVxuXG5zdmcge1xuICBtYXgtaGVpZ2h0OiAxMDAlO1xufVxuXG5waWN0dXJlLFxucGljdHVyZSBpbWcge1xuICBkaXNwbGF5OiBibG9jaztcbn1cblxuZmlndXJlIHtcbiAgcG9zaXRpb246IHJlbGF0aXZlO1xuICBkaXNwbGF5OiBpbmxpbmUtYmxvY2s7XG4gIG92ZXJmbG93OiBoaWRkZW47XG59XG5cbmZpZ2NhcHRpb24ge1xuICBhIHtcbiAgICBkaXNwbGF5OiBibG9jaztcbiAgfVxufVxuXG4vKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJFBSSU5UIFNUWUxFU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG5AbWVkaWEgcHJpbnQge1xuICAqLFxuICAqOjpiZWZvcmUsXG4gICo6OmFmdGVyLFxuICAqOjpmaXJzdC1sZXR0ZXIsXG4gICo6OmZpcnN0LWxpbmUge1xuICAgIGJhY2tncm91bmQ6IHRyYW5zcGFyZW50ICFpbXBvcnRhbnQ7XG4gICAgY29sb3I6IGJsYWNrICFpbXBvcnRhbnQ7XG4gICAgYm94LXNoYWRvdzogbm9uZSAhaW1wb3J0YW50O1xuICAgIHRleHQtc2hhZG93OiBub25lICFpbXBvcnRhbnQ7XG4gIH1cblxuICBhLFxuICBhOnZpc2l0ZWQge1xuICAgIHRleHQtZGVjb3JhdGlvbjogdW5kZXJsaW5lO1xuICB9XG5cbiAgYVtocmVmXTo6YWZ0ZXIge1xuICAgIGNvbnRlbnQ6IFwiIChcIiBhdHRyKGhyZWYpIFwiKVwiO1xuICB9XG5cbiAgYWJiclt0aXRsZV06OmFmdGVyIHtcbiAgICBjb250ZW50OiBcIiAoXCIgYXR0cih0aXRsZSkgXCIpXCI7XG4gIH1cblxuICAvKlxuICAgKiBEb24ndCBzaG93IGxpbmtzIHRoYXQgYXJlIGZyYWdtZW50IGlkZW50aWZpZXJzLFxuICAgKiBvciB1c2UgdGhlIGBqYXZhc2NyaXB0OmAgcHNldWRvIHByb3RvY29sXG4gICAqL1xuICBhW2hyZWZePVwiI1wiXTo6YWZ0ZXIsXG4gIGFbaHJlZl49XCJqYXZhc2NyaXB0OlwiXTo6YWZ0ZXIge1xuICAgIGNvbnRlbnQ6IFwiXCI7XG4gIH1cblxuICBwcmUsXG4gIGJsb2NrcXVvdGUge1xuICAgIGJvcmRlcjogMXB4IHNvbGlkICM5OTk7XG4gICAgcGFnZS1icmVhay1pbnNpZGU6IGF2b2lkO1xuICB9XG5cbiAgLypcbiAgICogUHJpbnRpbmcgVGFibGVzOlxuICAgKiBodHRwOi8vY3NzLWRpc2N1c3MuaW5jdXRpby5jb20vd2lraS9QcmludGluZ19UYWJsZXNcbiAgICovXG4gIHRoZWFkIHtcbiAgICBkaXNwbGF5OiB0YWJsZS1oZWFkZXItZ3JvdXA7XG4gIH1cblxuICB0cixcbiAgaW1nIHtcbiAgICBwYWdlLWJyZWFrLWluc2lkZTogYXZvaWQ7XG4gIH1cblxuICBpbWcge1xuICAgIG1heC13aWR0aDogMTAwJSAhaW1wb3J0YW50O1xuICAgIGhlaWdodDogYXV0bztcbiAgfVxuXG4gIHAsXG4gIGgyLFxuICBoMyB7XG4gICAgb3JwaGFuczogMztcbiAgICB3aWRvd3M6IDM7XG4gIH1cblxuICBoMixcbiAgaDMge1xuICAgIHBhZ2UtYnJlYWstYWZ0ZXI6IGF2b2lkO1xuICB9XG5cbiAgLm5vLXByaW50LFxuICAuYy1tYWluLWhlYWRlcixcbiAgLmMtbWFpbi1mb290ZXIsXG4gIC5hZCB7XG4gICAgZGlzcGxheTogbm9uZTtcbiAgfVxufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRUQUJMRVNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxudGFibGUge1xuICBib3JkZXItY29sbGFwc2U6IGNvbGxhcHNlO1xuICBib3JkZXItc3BhY2luZzogMDtcbiAgYm9yZGVyOiAxcHggc29saWQgJGMtZ3JheTtcbiAgd2lkdGg6IDEwMCU7XG59XG5cbnRoIHtcbiAgdGV4dC1hbGlnbjogbGVmdDtcbiAgYm9yZGVyOiAxcHggc29saWQgdHJhbnNwYXJlbnQ7XG4gIHBhZGRpbmc6ICgkc3BhY2UgLyAyKSAwO1xuICB0ZXh0LXRyYW5zZm9ybTogdXBwZXJjYXNlO1xuICB2ZXJ0aWNhbC1hbGlnbjogdG9wO1xuICBmb250LXdlaWdodDogYm9sZDtcbn1cblxudHIge1xuICBib3JkZXI6IDFweCBzb2xpZCB0cmFuc3BhcmVudDtcbn1cblxudGQge1xuICBib3JkZXI6IDFweCBzb2xpZCB0cmFuc3BhcmVudDtcbiAgcGFkZGluZzogJHNwYWNlIC8gMjtcbn1cblxuLyoqXG4gKiBSZXNwb25zaXZlIFRhYmxlXG4gKi9cbi5jLXRhYmxlLS1yZXNwb25zaXZlIHtcbiAgYm9yZGVyOiAxcHggc29saWQgJGMtZ3JheTtcbiAgYm9yZGVyLWNvbGxhcHNlOiBjb2xsYXBzZTtcbiAgcGFkZGluZzogMDtcbiAgd2lkdGg6IDEwMCU7XG5cbiAgdHIge1xuICAgIGJvcmRlcjogMXB4IHNvbGlkICRjLWdyYXk7XG4gICAgYmFja2dyb3VuZC1jb2xvcjogJGMtZ3JheS0tbGlnaHQ7XG4gIH1cblxuICB0aCxcbiAgdGQge1xuICAgIHBhZGRpbmc6ICRzcGFjZSAvIDI7XG4gIH1cblxuICB0aCB7XG4gICAgZm9udC1zaXplOiAkZm9udC1zaXplLXhzO1xuICAgIHRleHQtdHJhbnNmb3JtOiB1cHBlcmNhc2U7XG4gICAgYm9yZGVyLWJvdHRvbTogMXB4IHNvbGlkICRjLWdyYXk7XG4gIH1cblxuICBAaW5jbHVkZSBtZWRpYShcIjw9bWVkaXVtXCIpIHtcbiAgICBib3JkZXI6IDA7XG5cbiAgICB0aGVhZCB7XG4gICAgICBib3JkZXI6IG5vbmU7XG4gICAgICBjbGlwOiByZWN0KDAgMCAwIDApO1xuICAgICAgaGVpZ2h0OiAxcHg7XG4gICAgICBtYXJnaW46IC0xcHg7XG4gICAgICBvdmVyZmxvdzogaGlkZGVuO1xuICAgICAgcGFkZGluZzogMDtcbiAgICAgIHBvc2l0aW9uOiBhYnNvbHV0ZTtcbiAgICAgIHdpZHRoOiAxcHg7XG4gICAgfVxuXG4gICAgdHIge1xuICAgICAgYm9yZGVyLWJvdHRvbTogM3B4IHNvbGlkICRjLWdyYXk7XG4gICAgICBkaXNwbGF5OiBibG9jaztcbiAgICAgIG1hcmdpbi1ib3R0b206ICRzcGFjZSAvIDI7XG5cbiAgICAgICYudGhpcy1pcy1hY3RpdmUge1xuICAgICAgICB0ZDpub3QoOmZpcnN0LWNoaWxkKSB7XG4gICAgICAgICAgZGlzcGxheTogZmxleDtcbiAgICAgICAgfVxuXG4gICAgICAgIHRkOmZpcnN0LWNoaWxkOjpiZWZvcmUge1xuICAgICAgICAgIGNvbnRlbnQ6IFwiLSBcIiBhdHRyKGRhdGEtbGFiZWwpO1xuICAgICAgICB9XG4gICAgICB9XG4gICAgfVxuXG4gICAgdGQge1xuICAgICAgYm9yZGVyLWJvdHRvbTogMXB4IHNvbGlkICRjLWdyYXk7XG4gICAgICBkaXNwbGF5OiBmbGV4O1xuICAgICAgYWxpZ24taXRlbXM6IGNlbnRlcjtcbiAgICAgIGp1c3RpZnktY29udGVudDogc3BhY2UtYmV0d2VlbjtcbiAgICAgIG1pbi1oZWlnaHQ6IDQwcHg7XG5cbiAgICAgICY6Zmlyc3QtY2hpbGQge1xuICAgICAgICBjdXJzb3I6IHBvaW50ZXI7XG5cbiAgICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgICBjb250ZW50OiBcIisgXCIgYXR0cihkYXRhLWxhYmVsKTtcbiAgICAgICAgfVxuICAgICAgfVxuXG4gICAgICAmOmxhc3QtY2hpbGQge1xuICAgICAgICBib3JkZXItYm90dG9tOiAwO1xuICAgICAgfVxuXG4gICAgICAmOm5vdCg6Zmlyc3QtY2hpbGQpIHtcbiAgICAgICAgZGlzcGxheTogbm9uZTtcbiAgICAgIH1cblxuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgY29udGVudDogYXR0cihkYXRhLWxhYmVsKTtcbiAgICAgICAgZm9udC13ZWlnaHQ6IGJvbGQ7XG4gICAgICAgIHRleHQtdHJhbnNmb3JtOiB1cHBlcmNhc2U7XG4gICAgICAgIGZvbnQtc2l6ZTogJGZvbnQtc2l6ZS14cztcbiAgICAgIH1cbiAgICB9XG4gIH1cbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkVEVYVCBFTEVNRU5UU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4vKipcbiAqIFRleHQtUmVsYXRlZCBFbGVtZW50c1xuICovXG5wIHtcbiAgQGluY2x1ZGUgcDtcbn1cblxuc21hbGwge1xuICBmb250LXNpemU6IDkwJTtcbn1cblxuLyoqXG4gKiBCb2xkXG4gKi9cbnN0cm9uZyxcbmIge1xuICBmb250LXdlaWdodDogYm9sZDtcbn1cblxuLyoqXG4gKiBCbG9ja3F1b3RlXG4gKi9cbmJsb2NrcXVvdGUge1xuICBkaXNwbGF5OiBmbGV4O1xuICBmbGV4LXdyYXA6IHdyYXA7XG5cbiAgJjo6YmVmb3JlIHtcbiAgICBjb250ZW50OiBcIlxcMjAxQ1wiO1xuICAgIGZvbnQtZmFtaWx5OiAkZmYtZm9udDtcbiAgICBmb250LXNpemU6IDQwcHg7XG4gICAgbGluZS1oZWlnaHQ6IDE7XG4gICAgY29sb3I6ICRjLXNlY29uZGFyeTtcbiAgICBtaW4td2lkdGg6IDQwcHg7XG4gICAgYm9yZGVyLXJpZ2h0OiA2cHggc29saWQgJGMtYm9yZGVyO1xuICAgIGRpc3BsYXk6IGJsb2NrO1xuICAgIG1hcmdpbi1yaWdodDogJHNwYWNlO1xuICB9XG5cbiAgcCB7XG4gICAgbGluZS1oZWlnaHQ6IDEuNztcbiAgICBmbGV4OiAxO1xuICB9XG59XG5cbi8qKlxuICogSG9yaXpvbnRhbCBSdWxlXG4gKi9cbmhyIHtcbiAgaGVpZ2h0OiAxcHg7XG4gIGJvcmRlcjogbm9uZTtcbiAgYmFja2dyb3VuZC1jb2xvcjogcmdiYSgkYy1ncmF5LS1saWdodCwgMC41KTtcbiAgbWFyZ2luOiAwIGF1dG87XG59XG5cbi8qKlxuICogQWJicmV2aWF0aW9uXG4gKi9cbmFiYnIge1xuICBib3JkZXItYm90dG9tOiAxcHggZG90dGVkICRjLWdyYXk7XG4gIGN1cnNvcjogaGVscDtcbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkR1JJRFNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLmwtZ3JpZCB7XG4gIGRpc3BsYXk6IGdyaWQ7XG4gIGdyaWQtdGVtcGxhdGUtcm93czogYXV0bztcbiAgZ3JpZC1jb2x1bW4tZ2FwOiAkc3BhY2U7XG4gIGdyaWQtcm93LWdhcDogJHNwYWNlO1xuXG4gIEBtZWRpYSBhbGwgYW5kICgtbXMtaGlnaC1jb250cmFzdDogbm9uZSkge1xuICAgIGRpc3BsYXk6IGZsZXg7XG4gICAgZmxleC1kaXJlY3Rpb246IHJvdztcbiAgICBmbGV4LXdyYXA6IHdyYXA7XG4gICAgbWFyZ2luLWxlZnQ6IC0kc3BhY2U7XG4gICAgbWFyZ2luLXJpZ2h0OiAtJHNwYWNlO1xuXG4gICAgPiAqIHtcbiAgICAgIG1hcmdpbjogJHNwYWNlO1xuICAgIH1cbiAgfVxuXG4gICYtaXRlbSB7XG4gICAgcG9zaXRpb246IHJlbGF0aXZlO1xuICB9XG5cbiAgJi0tbGFyZ2UtZ3V0dGVycyB7XG4gICAgZ3JpZC1jb2x1bW4tZ2FwOiAkc3BhY2UtcXVhZDtcbiAgICBncmlkLXJvdy1nYXA6ICRzcGFjZS1xdWFkO1xuICB9XG5cbiAgJi0tMnVwIHtcbiAgICBAaW5jbHVkZSBtZWRpYShcIj5zbWFsbFwiKSB7XG4gICAgICBncmlkLXRlbXBsYXRlLWNvbHVtbnM6IHJlcGVhdCgyLCAxZnIpO1xuICAgIH1cblxuXG4gICAgQG1lZGlhIGFsbCBhbmQgKC1tcy1oaWdoLWNvbnRyYXN0OiBub25lKSB7XG4gICAgICA+ICoge1xuICAgICAgICB3aWR0aDogY2FsYyg1MCUgLSAjeyRzcGFjZS1kb3VibGV9KTtcbiAgICAgIH1cbiAgICB9XG5cbiAgICAmLS1mbGV4IHtcbiAgICAgIGRpc3BsYXk6IGZsZXg7XG4gICAgICBmbGV4LXdyYXA6IHdyYXA7XG4gICAgICBtYXJnaW46IDAgY2FsYygjeyRzcGFjZX0gKiAtMSk7XG5cbiAgICAgID4gKiB7XG4gICAgICAgIHdpZHRoOiAxMDAlO1xuICAgICAgICBwYWRkaW5nLWxlZnQ6ICRzcGFjZTtcbiAgICAgICAgcGFkZGluZy1yaWdodDogJHNwYWNlO1xuICAgICAgICBtYXJnaW4tdG9wOiAkc3BhY2UgKiAyO1xuXG4gICAgICAgIEBpbmNsdWRlIG1lZGlhKFwiPnNtYWxsXCIpIHtcbiAgICAgICAgICB3aWR0aDogNTAlO1xuICAgICAgICB9XG4gICAgICB9XG4gICAgfVxuICB9XG5cbiAgJi0tM3VwIHtcbiAgICBAaW5jbHVkZSBtZWRpYShcIj5zbWFsbFwiKSB7XG4gICAgICBncmlkLXRlbXBsYXRlLWNvbHVtbnM6IHJlcGVhdCgyLCAxZnIpO1xuICAgIH1cblxuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+bGFyZ2VcIikge1xuICAgICAgZ3JpZC10ZW1wbGF0ZS1jb2x1bW5zOiByZXBlYXQoMywgMWZyKTtcbiAgICB9XG5cblxuICAgIEBtZWRpYSBhbGwgYW5kICgtbXMtaGlnaC1jb250cmFzdDogbm9uZSkge1xuICAgICAgPiAqIHtcbiAgICAgICAgd2lkdGg6IGNhbGMoMzMuMzMzJSAtICN7JHNwYWNlLWRvdWJsZX0pO1xuICAgICAgfVxuICAgIH1cbiAgfVxuXG4gICYtLTR1cCB7XG4gICAgZ3JpZC10ZW1wbGF0ZS1jb2x1bW5zOiByZXBlYXQobWlubWF4KDIwMHB4LCAxZnIpKTtcblxuICAgIEBpbmNsdWRlIG1lZGlhKFwiPnhzbWFsbFwiKSB7XG4gICAgICBncmlkLXRlbXBsYXRlLWNvbHVtbnM6IHJlcGVhdCgyLCAxZnIpO1xuICAgIH1cblxuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+bWVkaXVtXCIpIHtcbiAgICAgIGdyaWQtdGVtcGxhdGUtY29sdW1uczogcmVwZWF0KDMsIDFmcik7XG4gICAgfVxuXG5cbiAgICBAaW5jbHVkZSBtZWRpYShcIj54bGFyZ2VcIikge1xuICAgICAgZ3JpZC10ZW1wbGF0ZS1jb2x1bW5zOiByZXBlYXQoNCwgMWZyKTtcbiAgICB9XG5cblxuICAgIEBtZWRpYSBhbGwgYW5kICgtbXMtaGlnaC1jb250cmFzdDogbm9uZSkge1xuICAgICAgPiAqIHtcbiAgICAgICAgd2lkdGg6IGNhbGMoMjUlIC0gI3skc3BhY2UtZG91YmxlfSk7XG4gICAgICB9XG4gICAgfVxuICB9XG5cbiAgJi0tNHVwLS1hdC1tZWRpdW0ge1xuICAgIGdyaWQtdGVtcGxhdGUtY29sdW1uczogcmVwZWF0KDIsIDFmcik7XG5cbiAgICBAaW5jbHVkZSBtZWRpYShcIj5zbWFsbFwiKSB7XG4gICAgICBncmlkLXRlbXBsYXRlLWNvbHVtbnM6IHJlcGVhdCgzLCAxZnIpO1xuICAgIH1cblxuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+bWVkaXVtXCIpIHtcbiAgICAgIGdyaWQtdGVtcGxhdGUtY29sdW1uczogcmVwZWF0KDQsIDFmcik7XG4gICAgfVxuXG5cbiAgICBAbWVkaWEgYWxsIGFuZCAoLW1zLWhpZ2gtY29udHJhc3Q6IG5vbmUpIHtcbiAgICAgID4gKiB7XG4gICAgICAgIHdpZHRoOiBjYWxjKDI1JSAtICN7JHNwYWNlLWRvdWJsZX0pO1xuICAgICAgfVxuICAgIH1cbiAgfVxuXG4gICYtLTV1cCB7XG4gICAgZ3JpZC1yb3ctZ2FwOiAkc3BhY2UgKiAyO1xuICAgIGdyaWQtdGVtcGxhdGUtY29sdW1uczogcmVwZWF0KGF1dG8tZml0LCBtaW5tYXgoMTMwcHgsIDFmcikpO1xuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+bGFyZ2VcIikge1xuICAgICAgZ3JpZC10ZW1wbGF0ZS1jb2x1bW5zOiByZXBlYXQoNSwgMWZyKTtcbiAgICB9XG5cblxuICAgIEBtZWRpYSBhbGwgYW5kICgtbXMtaGlnaC1jb250cmFzdDogbm9uZSkge1xuICAgICAgPiAqIHtcbiAgICAgICAgd2lkdGg6IGNhbGMoMjAlIC0gI3skc3BhY2UtZG91YmxlfSk7XG4gICAgICB9XG4gICAgfVxuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJFdSQVBQRVJTICYgQ09OVEFJTkVSU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4vKipcbiAqIFdyYXBwaW5nIGVsZW1lbnQgdG8ga2VlcCBjb250ZW50IGNvbnRhaW5lZCBhbmQgY2VudGVyZWQuXG4gKi9cbi5sLXdyYXAge1xuICBtYXJnaW46IDAgYXV0bztcbiAgcGFkZGluZy1sZWZ0OiAkc3BhY2U7XG4gIHBhZGRpbmctcmlnaHQ6ICRzcGFjZTtcbiAgd2lkdGg6IDEwMCU7XG4gIHBvc2l0aW9uOiByZWxhdGl2ZTtcblxuICBAaW5jbHVkZSBtZWRpYShcIj54eGxhcmdlXCIpIHtcbiAgICBwYWRkaW5nLWxlZnQ6ICRzcGFjZS1kb3VibGU7XG4gICAgcGFkZGluZy1yaWdodDogJHNwYWNlLWRvdWJsZTtcbiAgfVxufVxuXG4vKipcbiAqIExheW91dCBjb250YWluZXJzIC0ga2VlcCBjb250ZW50IGNlbnRlcmVkIGFuZCB3aXRoaW4gYSBtYXhpbXVtIHdpZHRoLiBBbHNvXG4gKiBhZGp1c3RzIGxlZnQgYW5kIHJpZ2h0IHBhZGRpbmcgYXMgdGhlIHZpZXdwb3J0IHdpZGVucy5cbiAqL1xuXG4ubC1jb250YWluZXIge1xuICBtYXgtd2lkdGg6ICRtYXgtd2lkdGg7XG4gIG1hcmdpbi1sZWZ0OiBhdXRvO1xuICBtYXJnaW4tcmlnaHQ6IGF1dG87XG4gIHBvc2l0aW9uOiByZWxhdGl2ZTtcblxuICAmLS14bCB7XG4gICAgbWF4LXdpZHRoOiAkbWF4LXdpZHRoLXhsO1xuICB9XG59XG5cbi8qKlxuICogR3JpZCBjbGFzc2VzXG4gKi9cbkBmb3IgJGkgZnJvbSAxIHRocm91Z2ggJGdyaWQtY29sdW1ucyB7XG4gIC5sLWNvbnRhaW5lci0tI3skaX1jb2wge1xuICAgIEBpZiAkaSA9PSAxIHtcbiAgICAgIG1heC13aWR0aDogJGNvbC13aWR0aCAqICRpICsgJGd1dHRlciAqICRpICsgcHg7XG4gICAgfVxuICAgIEBlbHNlIGlmICRpID09IDIge1xuICAgICAgbWF4LXdpZHRoOiAkY29sLXdpZHRoICogJGkgKyAkZ3V0dGVyICogMSArIHB4O1xuICAgIH1cbiAgICBAZWxzZSB7XG4gICAgICBtYXgtd2lkdGg6ICRjb2wtd2lkdGggKiAkaSArICRndXR0ZXIgKiAoJGkgLSAyKSArIHB4O1xuICAgIH1cbiAgfVxufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRURVhUIFRZUEVTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbi8qKlxuICogRm9udCBGYW1pbGllc1xuICovXG4udS1mb250IHtcbiAgZm9udC1mYW1pbHk6ICRmZi1mb250O1xufVxuXG4udS1mb250LS1wcmltYXJ5LFxuLnUtZm9udC0tcHJpbWFyeSBwIHtcbiAgZm9udC1mYW1pbHk6ICRmZi1mb250LS1wcmltYXJ5O1xufVxuXG4udS1mb250LS1zZWNvbmRhcnksXG4udS1mb250LS1zZWNvbmRhcnkgcCB7XG4gIGZvbnQtZmFtaWx5OiAkZmYtZm9udC0tc2Vjb25kYXJ5O1xufVxuXG4vKipcbiAqIFRleHQgU2l6ZXNcbiAqL1xuXG4udS1mb250LS14cyB7XG4gIGZvbnQtc2l6ZTogJGZvbnQtc2l6ZS14cztcbn1cblxuLnUtZm9udC0tcyB7XG4gIGZvbnQtc2l6ZTogJGZvbnQtc2l6ZS1zO1xufVxuXG4udS1mb250LS1tIHtcbiAgZm9udC1zaXplOiAkZm9udC1zaXplLW07XG59XG5cbi51LWZvbnQtLWwge1xuICBmb250LXNpemU6ICRmb250LXNpemUtbDtcbn1cblxuLnUtZm9udC0teGwge1xuICBmb250LXNpemU6ICRmb250LXNpemUteGw7XG59XG5cbi51LWZvbnQtLXh4bCB7XG4gIGZvbnQtc2l6ZTogJGZvbnQtc2l6ZS14eGw7XG59XG5cbi8qKlxuICogVGV4dCBUcmFuc2Zvcm1zXG4gKi9cbi51LXRleHQtdHJhbnNmb3JtLS11cHBlciB7XG4gIHRleHQtdHJhbnNmb3JtOiB1cHBlcmNhc2U7XG59XG5cbi51LXRleHQtdHJhbnNmb3JtLS1sb3dlciB7XG4gIHRleHQtdHJhbnNmb3JtOiBsb3dlcmNhc2U7XG59XG5cbi8qKlxuICogVGV4dCBTdHlsZXNcbiAqL1xuLnUtdGV4dC1zdHlsZS0taXRhbGljIHtcbiAgZm9udC1zdHlsZTogaXRhbGljO1xufVxuXG4udS1mb250LXdlaWdodC0tbm9ybWFsIHtcbiAgZm9udC13ZWlnaHQ6IG5vcm1hbDtcbn1cblxuLyoqXG4gKiBUZXh0IFBvc2l0aW9uaW5nXG4gKi9cbi51LWFsaWduLS1jZW50ZXIge1xuICB0ZXh0LWFsaWduOiBjZW50ZXI7XG59XG5cbi8qKlxuICogVGV4dCBEZWNvcmF0aW9uc1xuICovXG4udS10ZXh0LWRlY29yYXRpb24tLXVuZGVybGluZSB7XG4gIHRleHQtZGVjb3JhdGlvbjogdW5kZXJsaW5lO1xufVxuXG4vKipcbiAqIFJpY2ggdGV4dCBlZGl0b3IgdGV4dFxuICovXG4uby1ydGUtdGV4dCB7XG4gIHdpZHRoOiAxMDAlO1xuICBtYXJnaW46IDAgYXV0bztcblxuICAmID4gKiArICoge1xuICAgIG1hcmdpbi10b3A6ICRzcGFjZTtcbiAgfVxuXG4gID4gZGwgZGQsXG4gID4gZGwgZHQsXG4gID4gb2wgbGksXG4gID4gdWwgbGksXG4gID4gcCB7XG4gICAgQGluY2x1ZGUgcDtcbiAgfVxuXG4gID4gaDEsXG4gID4gaDIsXG4gID4gaDMsXG4gID4gaDQsXG4gID4gaDUsXG4gID4gaDYge1xuICAgIHBhZGRpbmctdG9wOiAkc3BhY2U7XG4gICAgbWFyZ2luLWJvdHRvbTogLSRzcGFjZS1oYWxmO1xuICB9XG5cbiAgaDI6ZW1wdHksXG4gIGgzOmVtcHR5LFxuICBwOmVtcHR5IHtcbiAgICBkaXNwbGF5OiBub25lO1xuICB9XG5cbiAgLy8gSGVhZGluZyArIHN1YmhlYWRpbmcgbG9ja3VwXG4gID4gaDIgKyBoMyB7XG4gICAgbWFyZ2luLXRvcDogMDtcbiAgICBwYWRkaW5nLXRvcDogJHNwYWNlLWhhbGY7XG4gIH1cblxuICBhIHtcbiAgICB0ZXh0LWRlY29yYXRpb246IHVuZGVybGluZTtcbiAgfVxuXG4gIGhyIHtcbiAgICBtYXJnaW4tdG9wOiAkc3BhY2UtZG91YmxlO1xuICAgIG1hcmdpbi1ib3R0b206ICRzcGFjZS1kb3VibGU7XG4gIH1cblxuICBjb2RlLFxuICBwcmUge1xuICAgIGZvbnQtc2l6ZTogMTI1JTtcbiAgfVxuXG4gIG9sLFxuICB1bCB7XG4gICAgcGFkZGluZy1sZWZ0OiAwO1xuICAgIG1hcmdpbi1sZWZ0OiAwO1xuXG4gICAgbGkge1xuICAgICAgbGlzdC1zdHlsZTogbm9uZTtcbiAgICAgIHBhZGRpbmctbGVmdDogMzRweDtcbiAgICAgIG1hcmdpbi1sZWZ0OiAwO1xuICAgICAgcG9zaXRpb246IHJlbGF0aXZlO1xuICAgICAgbGluZS1oZWlnaHQ6IDIuMWVtO1xuXG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBjb2xvcjogJGMtc2Vjb25kYXJ5O1xuICAgICAgICB3aWR0aDogMTBweDtcbiAgICAgICAgZGlzcGxheTogaW5saW5lLWJsb2NrO1xuICAgICAgICBwb3NpdGlvbjogYWJzb2x1dGU7XG4gICAgICAgIGxlZnQ6IDA7XG4gICAgICAgIGZvbnQtc2l6ZTogMjRweDtcbiAgICAgICAgbGluZS1oZWlnaHQ6IDE7XG4gICAgICAgIHRvcDogNHB4O1xuICAgICAgfVxuXG4gICAgICBsaSB7XG4gICAgICAgIGxpc3Qtc3R5bGU6IG5vbmU7XG4gICAgICB9XG4gICAgfVxuICB9XG5cbiAgb2wge1xuICAgIGNvdW50ZXItcmVzZXQ6IGl0ZW07XG5cbiAgICBsaSB7XG4gICAgICAmOjpiZWZvcmUge1xuICAgICAgICBjb250ZW50OiBjb3VudGVyKGl0ZW0pIFwiLiBcIjtcbiAgICAgICAgY291bnRlci1pbmNyZW1lbnQ6IGl0ZW07XG4gICAgICB9XG5cbiAgICAgIGxpIHtcbiAgICAgICAgY291bnRlci1yZXNldDogaXRlbTtcblxuICAgICAgICAmOjpiZWZvcmUge1xuICAgICAgICAgIGNvbnRlbnQ6ICdcXDAwMjAxMCc7XG4gICAgICAgIH1cbiAgICAgIH1cbiAgICB9XG4gIH1cblxuICB1bCB7XG4gICAgbGkge1xuICAgICAgJjo6YmVmb3JlIHtcbiAgICAgICAgY29udGVudDogJ1xcMDAyMDIyJztcbiAgICAgIH1cblxuICAgICAgbGkge1xuICAgICAgICAmOjpiZWZvcmUge1xuICAgICAgICAgIGNvbnRlbnQ6ICdcXDAwMjVFNic7XG4gICAgICAgIH1cbiAgICAgIH1cbiAgICB9XG4gIH1cbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkQlVUVE9OU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG5AbWl4aW4gby1idXR0b24ge1xuICBkaXNwbGF5OiBpbmxpbmUtZmxleDtcbiAgcG9zaXRpb246IHJlbGF0aXZlO1xuICBqdXN0aWZ5LWNvbnRlbnQ6IGNlbnRlcjtcbiAgYWxpZ24taXRlbXM6IGNlbnRlcjtcbiAgdHJhbnNpdGlvbjogJHRyYW5zaXRpb24tYWxsO1xuICB0ZXh0LWRlY29yYXRpb246IG5vbmU7XG4gIGJvcmRlci1yYWRpdXM6IDA7XG4gIGZvbnQtc2l6ZTogJGJvZHktZm9udC1zaXplO1xuICBmb250LWZhbWlseTogJGZmLWZvbnQtLXByaW1hcnk7XG4gIGZvbnQtd2VpZ2h0OiBib2xkO1xuICB0ZXh0LWFsaWduOiBjZW50ZXI7XG4gIGxpbmUtaGVpZ2h0OiAxO1xuICB3aGl0ZS1zcGFjZTogbm93cmFwO1xuICBhcHBlYXJhbmNlOiBub25lO1xuICBib3JkZXI6IG5vbmU7XG4gIGN1cnNvcjogcG9pbnRlcjtcbiAgcGFkZGluZzogJHNwYWNlICRzcGFjZS1kb3VibGU7XG4gIHRleHQtdHJhbnNmb3JtOiB1cHBlcmNhc2U7XG59XG5cbi8qKlxuICogQnV0dG9uIFByaW1hcnlcbiAqL1xuQG1peGluIG8tYnV0dG9uLS1wcmltYXJ5IHtcbiAgYmFja2dyb3VuZC1jb2xvcjogJGMtc2Vjb25kYXJ5O1xuICBjb2xvcjogJGMtd2hpdGU7XG4gIGZpbHRlcjogYnJpZ2h0bmVzcygxMDAlKTtcblxuICAmOmhvdmVyLFxuICAmOmZvY3VzIHtcbiAgICBmaWx0ZXI6IGJyaWdodG5lc3MoMTIwJSk7XG4gIH1cbn1cblxuLm8tYnV0dG9uLS1wcmltYXJ5IHtcbiAgQGluY2x1ZGUgby1idXR0b247XG4gIEBpbmNsdWRlIG8tYnV0dG9uLS1wcmltYXJ5O1xufVxuXG4vKipcbiAqIEJ1dHRvbiBTZWNvbmRhcnlcbiAqL1xuQG1peGluIG8tYnV0dG9uLS1zZWNvbmRhcnkge1xuICBiYWNrZ3JvdW5kLWNvbG9yOiAkYy1zZWNvbmRhcnk7XG4gIGNvbG9yOiAkYy13aGl0ZTtcbiAgZmlsdGVyOiBicmlnaHRuZXNzKDEwMCUpO1xuXG4gICY6aG92ZXIsXG4gICY6Zm9jdXMge1xuICAgIGZpbHRlcjogYnJpZ2h0bmVzcygxMjAlKTtcbiAgfVxufVxuXG4uby1idXR0b24tLXNlY29uZGFyeSB7XG4gIEBpbmNsdWRlIG8tYnV0dG9uO1xuICBAaW5jbHVkZSBvLWJ1dHRvbi0tc2Vjb25kYXJ5O1xufVxuXG5idXR0b24sXG5pbnB1dFt0eXBlPVwic3VibWl0XCJdLFxuLm8tYnV0dG9uIHtcbiAgQGluY2x1ZGUgby1idXR0b247XG4gIEBpbmNsdWRlIG8tYnV0dG9uLS1wcmltYXJ5O1xufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRCTE9DS1NcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkQ0FSRFNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkSEVST1NcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkUEFHRSBTRUNUSU9OU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRTUEVDSUZJQyBGT1JNU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4vKipcbiAqIFZhbGlkYXRpb25cbiAqL1xuLmhhcy1lcnJvciB7XG4gIGJvcmRlci1jb2xvcjogJGMtZXJyb3IgIWltcG9ydGFudDtcbn1cblxuLmlzLXZhbGlkIHtcbiAgYm9yZGVyLWNvbG9yOiAkYy12YWxpZCAhaW1wb3J0YW50O1xufVxuXG4vKipcbiAqIE5ld3NsZXR0ZXIgU2lnbnVwXG4gKi9cbi5vLW5ld3NsZXR0ZXItc2lnbnVwIHtcbiAgZGlzcGxheTogZmxleDtcbiAgZmxleC1kaXJlY3Rpb246IGNvbHVtbjtcblxuICBAaW5jbHVkZSBtZWRpYShcIj5tZWRpdW1cIikge1xuICAgIGZsZXgtZGlyZWN0aW9uOiByb3c7XG4gIH1cblxuXG4gIGlucHV0W3R5cGU9ZW1haWxdIHtcbiAgICB3aWR0aDogMTAwJTtcbiAgICBib3JkZXI6IG5vbmU7XG5cbiAgICBAaW5jbHVkZSBtZWRpYShcIj5tZWRpdW1cIikge1xuICAgICAgd2lkdGg6IGNhbGMoMTAwJSAtIDE4MHB4KTtcbiAgICB9XG4gIH1cblxuICBpbnB1dFt0eXBlPXN1Ym1pdF0ge1xuICAgIHdpZHRoOiAxMDAlO1xuICAgIG1hcmdpbi10b3A6ICRzcGFjZS1oYWxmO1xuXG4gICAgQGluY2x1ZGUgbWVkaWEoXCI+bWVkaXVtXCIpIHtcbiAgICAgIG1hcmdpbi10b3A6IDA7XG4gICAgICB3aWR0aDogMTgwcHg7XG4gICAgfVxuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJElDT05TXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbi8qKlxuICogSWNvbiBTaXppbmdcbiAqL1xuLm8taWNvbiB7XG4gIGRpc3BsYXk6IGlubGluZS1ibG9jaztcbn1cblxuLnUtaWNvbi0teHMge1xuICB3aWR0aDogJGljb24teHNtYWxsO1xuICBoZWlnaHQ6ICRpY29uLXhzbWFsbDtcbn1cblxuLnUtaWNvbi0tcyB7XG4gIHdpZHRoOiAkaWNvbi1zbWFsbDtcbiAgaGVpZ2h0OiAkaWNvbi1zbWFsbDtcbn1cblxuLnUtaWNvbi0tbSB7XG4gIHdpZHRoOiAkaWNvbi1tZWRpdW07XG4gIGhlaWdodDogJGljb24tbWVkaXVtO1xufVxuXG4udS1pY29uLS1sIHtcbiAgd2lkdGg6ICRpY29uLWxhcmdlO1xuICBoZWlnaHQ6ICRpY29uLWxhcmdlO1xufVxuXG4udS1pY29uLS14bCB7XG4gIHdpZHRoOiAkaWNvbi14bGFyZ2U7XG4gIGhlaWdodDogJGljb24teGxhcmdlO1xufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRMSVNUIFRZUEVTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbi8qKlxuICogTnVtYmVyZWQgTGlzdFxuICovXG4uby1saXN0LS1udW1iZXJlZCB7XG4gIGNvdW50ZXItcmVzZXQ6IGl0ZW07XG5cbiAgbGkge1xuICAgIGRpc3BsYXk6IGJsb2NrO1xuXG4gICAgJjo6YmVmb3JlIHtcbiAgICAgIGNvbnRlbnQ6IGNvdW50ZXIoaXRlbSk7XG4gICAgICBjb3VudGVyLWluY3JlbWVudDogaXRlbTtcbiAgICAgIGNvbG9yOiAkYy13aGl0ZTtcbiAgICAgIHBhZGRpbmc6IDEwcHggMTVweDtcbiAgICAgIGJvcmRlci1yYWRpdXM6IDNweDtcbiAgICAgIGJhY2tncm91bmQtY29sb3I6ICRjLWJsYWNrO1xuICAgICAgZm9udC13ZWlnaHQ6IGJvbGQ7XG4gICAgICBtYXJnaW4tcmlnaHQ6ICRzcGFjZTtcbiAgICAgIGZsb2F0OiBsZWZ0O1xuICAgIH1cblxuICAgID4gKiB7XG4gICAgICBvdmVyZmxvdzogaGlkZGVuO1xuICAgIH1cblxuICAgIGxpIHtcbiAgICAgIGNvdW50ZXItcmVzZXQ6IGl0ZW07XG5cbiAgICAgICY6OmJlZm9yZSB7XG4gICAgICAgIGNvbnRlbnQ6IFwiXFwwMDIwMTBcIjtcbiAgICAgIH1cbiAgICB9XG4gIH1cbn1cblxuLyoqXG4gKiBCdWxsZXQgTGlzdFxuICovXG4uby1idWxsZXQtbGlzdCB7XG4gIGxpc3Qtc3R5bGUtdHlwZTogZGlzYztcbiAgcGFkZGluZy1sZWZ0OiAkc3BhY2U7XG5cbiAgbGkge1xuICAgIG92ZXJmbG93OiB2aXNpYmxlO1xuXG4gICAgJjpsYXN0LWNoaWxkIHtcbiAgICAgIG1hcmdpbi1ib3R0b206IDA7XG4gICAgfVxuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJE5BVklHQVRJT05cblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkTUVESUEgT0JKRUNUU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRBUlRJQ0xFICYgUkVMQVRFRCBDT01QT05FTlRTXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG5cbi5wYWdlLWlkLTUge1xuICBoZWlnaHQ6IDEwMHZoO1xuICBkaXNwbGF5OiBmbGV4O1xuICBmbGV4LWRpcmVjdGlvbjogY29sdW1uO1xuICBqdXN0aWZ5LWNvbnRlbnQ6IHNwYWNlLWJldHdlZW47XG4gIGJhY2tncm91bmQtY29sb3I6ICRjLXByaW1hcnk7XG5cbiAgLmMtbWFpbi1oZWFkZXIge1xuICAgIGJhY2tncm91bmQ6IHVybChcIi4uL2ltYWdlcy9oZWFkZXItYmtnLnBuZ1wiKSBjZW50ZXIgYm90dG9tIG5vLXJlcGVhdDtcbiAgICBiYWNrZ3JvdW5kLXNpemU6IGNvdmVyO1xuICB9XG59XG5cbi5sLWFydGljbGUtLWxhbmRpbmcge1xuICBwYWRkaW5nOiAwIDAgJHNwYWNlLWRvdWJsZSAwO1xuICBtYXgtd2lkdGg6ICRzbWFsbDtcbiAgdGV4dC1hbGlnbjogY2VudGVyO1xufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRHQUxMRVJZXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJEZPT1RFUlxuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4uYy1tYWluLWZvb3RlciB7XG4gIGJhY2tncm91bmQtY29sb3I6ICRjLXNlY29uZGFyeTtcbiAgY29sb3I6ICRjLXdoaXRlO1xuXG4gICYtLWlubmVyIHtcbiAgICBwYWRkaW5nOiAkc3BhY2UtaGFsZiAwO1xuICAgIHRleHQtYWxpZ246IGNlbnRlcjtcbiAgfVxufVxuIiwiLyogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICpcXFxuICAgICRIRUFERVJcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLm8tbG9nbyB7XG4gIG1hcmdpbjogJHNwYWNlLWRvdWJsZSAkc3BhY2UgJHNwYWNlLXF1YWQgJHNwYWNlO1xuXG4gIGltZyB7XG4gICAgd2lkdGg6IDEwMCU7XG4gICAgaGVpZ2h0OiBhdXRvO1xuICAgIG1heC13aWR0aDogJHhzbWFsbDtcbiAgICBtYXJnaW46IDAgYXV0bztcbiAgICBwb3NpdGlvbjogcmVsYXRpdmU7XG5cbiAgICBAaW5jbHVkZSBtZWRpYShcIj5tZWRpdW1cIikge1xuICAgICAgbGVmdDogLTE1cHg7XG4gICAgfVxuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJE1BSU4gQ09OVEVOVCBBUkVBXG5cXCogLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovXG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJEFOSU1BVElPTlMgJiBUUkFOU0lUSU9OU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4vKipcbiAqIFRyYW5zaXRpb25zXG4gKi9cbi5oYXMtdHJhbnMge1xuICB0cmFuc2l0aW9uOiBhbGwgMC40cyBlYXNlLWluLW91dDtcbn1cblxuLmhhcy10cmFucy0tZmFzdCB7XG4gIHRyYW5zaXRpb246IGFsbCAwLjFzIGVhc2UtaW4tb3V0O1xufVxuXG4uaGFzLXpvb20ge1xuICBvdmVyZmxvdzogaGlkZGVuO1xuXG4gIGltZyB7XG4gICAgdHJhbnNpdGlvbjogdHJhbnNmb3JtIDAuM3MgZWFzZS1vdXQ7XG4gICAgdHJhbnNmb3JtOiBzY2FsZSgxKTtcbiAgfVxuXG4gIGE6aG92ZXIge1xuICAgIGltZyB7XG4gICAgICB0cmFuc2Zvcm06IHNjYWxlKDEuMDMpO1xuICAgIH1cbiAgfVxufVxuXG4vKipcbiAqIEZhZGUgQ2xhc3Nlc1xuICovXG4uaGFzLWZhZGV1cCB7XG4gIG9wYWNpdHk6IDA7XG4gIHRyYW5zZm9ybTogdHJhbnNsYXRlKDAsIDI1cHgpO1xuICB0cmFuc2l0aW9uOiBhbGwgMC42cyBlYXNlLW91dCAwLjVzO1xufVxuXG4uZmFkZXVwLFxuLmhhcy1mYWRldXAuaXMtYWN0aXZlIHtcbiAgb3BhY2l0eTogMTtcbiAgdHJhbnNmb3JtOiB0cmFuc2xhdGUoMCwgMCk7XG59XG5cbi5oYXMtZmFkZWluIHtcbiAgb3BhY2l0eTogMDtcbiAgdHJhbnNpdGlvbjogYWxsIDAuOHMgZWFzZS1vdXQ7XG59XG5cbi5mYWRlaW4ge1xuICBvcGFjaXR5OiAxO1xufVxuXG4vLyBGYWRlIGltYWdlIGluIGFmdGVyIGxvYWQuXG4ubGF6eWxvYWQsXG4ubGF6eWxvYWRpbmcge1xuICBvcGFjaXR5OiAwO1xuICB0cmFuc2Zvcm06IHRyYW5zbGF0ZSgwLCAyNXB4KTtcbiAgdHJhbnNpdGlvbjogYWxsIDAuNnMgZWFzZS1vdXQ7XG59XG5cbi5sYXp5bG9hZGVkIHtcbiAgb3BhY2l0eTogMTtcbiAgdHJhbnNpdGlvbjogb3BhY2l0eSAzMDBtcztcbn1cblxuLy8gQm91bmNlIHVwIGFuZCBkb3duLlxuQGtleWZyYW1lcyBib3VuY2Uge1xuICAwJSxcbiAgMTAwJSB7XG4gICAgdHJhbnNmb3JtOiB0cmFuc2xhdGVZKDApO1xuICB9XG5cbiAgMjAlIHtcbiAgICB0cmFuc2Zvcm06IHRyYW5zbGF0ZVkoLTNweCk7XG4gIH1cblxuICA4MCUge1xuICAgIHRyYW5zZm9ybTogdHJhbnNsYXRlWSgzcHgpO1xuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJENPTE9SIE1PRElGSUVSU1xuXFwqIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqL1xuXG4vKipcbiAqIFRleHQgQ29sb3JzXG4gKi9cbi51LWNvbG9yLS1ibGFjayxcbi51LWNvbG9yLS1ibGFjayBhIHtcbiAgY29sb3I6ICRjLWJsYWNrO1xufVxuXG4udS1jb2xvci0tZ3JheSxcbi51LWNvbG9yLS1ncmF5IGEge1xuICBjb2xvcjogJGMtZ3JheTtcbn1cblxuLnUtY29sb3ItLWdyYXktLWxpZ2h0LFxuLnUtY29sb3ItLWdyYXktLWxpZ2h0IGEge1xuICBjb2xvcjogJGMtZ3JheS0tbGlnaHQ7XG59XG5cbi51LWNvbG9yLS13aGl0ZSxcbi51LWNvbG9yLS13aGl0ZSBhIHtcbiAgY29sb3I6ICRjLXdoaXRlICFpbXBvcnRhbnQ7XG59XG5cbi8qKlxuICogQmFja2dyb3VuZCBDb2xvcnNcbiAqL1xuLnUtYmFja2dyb3VuZC1jb2xvci0tbm9uZSB7XG4gIGJhY2tncm91bmQ6IG5vbmU7XG59XG5cbi51LWJhY2tncm91bmQtY29sb3ItLWJsYWNrIHtcbiAgYmFja2dyb3VuZC1jb2xvcjogJGMtYmxhY2s7XG59XG5cbi51LWJhY2tncm91bmQtY29sb3ItLWdyYXkge1xuICBiYWNrZ3JvdW5kLWNvbG9yOiAkYy1ncmF5O1xufVxuXG4udS1iYWNrZ3JvdW5kLWNvbG9yLS1ncmF5LS1saWdodCB7XG4gIGJhY2tncm91bmQtY29sb3I6ICRjLWdyYXktLWxpZ2h0O1xufVxuXG4udS1iYWNrZ3JvdW5kLWNvbG9yLS13aGl0ZSB7XG4gIGJhY2tncm91bmQtY29sb3I6ICRjLXdoaXRlO1xufVxuXG4vKipcbiAqIFNWRyBGaWxsIENvbG9yc1xuICovXG4udS1wYXRoLWZpbGwtLWJsYWNrIHtcbiAgcGF0aCB7XG4gICAgZmlsbDogJGMtYmxhY2s7XG4gIH1cbn1cblxuLnUtcGF0aC1maWxsLS1ncmF5IHtcbiAgcGF0aCB7XG4gICAgZmlsbDogJGMtZ3JheTtcbiAgfVxufVxuXG4udS1wYXRoLWZpbGwtLXdoaXRlIHtcbiAgcGF0aCB7XG4gICAgZmlsbDogJGMtd2hpdGU7XG4gIH1cbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkRElTUExBWSBTVEFURVNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLyoqXG4gKiBEaXNwbGF5IENsYXNzZXNcbiAqL1xuLnUtZGlzcGxheS0taW5saW5lLWJsb2NrIHtcbiAgZGlzcGxheTogaW5saW5lLWJsb2NrO1xufVxuXG4udS1kaXNwbGF5LS1ibG9jayB7XG4gIGRpc3BsYXk6IGJsb2NrO1xufVxuXG4udS1mbGV4IHtcbiAgZGlzcGxheTogZmxleDtcbn1cblxuLnUtanVzdGlmeS1jb250ZW50LS1zcGFjZS1iZXR3ZWVuIHtcbiAganVzdGlmeS1jb250ZW50OiBzcGFjZS1iZXR3ZWVuO1xufVxuXG4udS1qdXN0aWZ5LWNvbnRlbnQtLWZsZXgtZW5kIHtcbiAganVzdGlmeS1jb250ZW50OiBmbGV4LWVuZDtcbn1cblxuLnUtYWxpZ24taXRlbXMtLWNlbnRlciB7XG4gIGFsaWduLWl0ZW1zOiBjZW50ZXI7XG59XG5cbi51LWZsZXgtZGlyZWN0b24tLWNvbHVtbiB7XG4gIGZsZXgtZGlyZWN0aW9uOiBjb2x1bW47XG59XG5cbi8vIFNwZWNpZmljIFdpZHRocyAtIHZpc2libGUgZ3JlYXRlciB0aGFuICNcbi51LWhpZGUtdW50aWwtLXMge1xuICBAaW5jbHVkZSBtZWRpYShcIjw9c21hbGxcIikge1xuICAgIGRpc3BsYXk6IG5vbmU7XG4gIH1cbn1cblxuLnUtaGlkZS11bnRpbC0tbSB7XG4gIEBpbmNsdWRlIG1lZGlhKFwiPD1tZWRpdW1cIikge1xuICAgIGRpc3BsYXk6IG5vbmU7XG4gIH1cbn1cblxuLnUtaGlkZS11bnRpbC0tbCB7XG4gIEBpbmNsdWRlIG1lZGlhKFwiPD1sYXJnZVwiKSB7XG4gICAgZGlzcGxheTogbm9uZTtcbiAgfVxufVxuXG4udS1oaWRlLXVudGlsLS14bCB7XG4gIEBpbmNsdWRlIG1lZGlhKFwiPD14bGFyZ2VcIikge1xuICAgIGRpc3BsYXk6IG5vbmU7XG4gIH1cbn1cblxuLy8gU3BlY2lmaWMgV2lkdGhzIC0gaGlkZSBncmVhdGVyIHRoYW4gI1xuLnUtaGlkZS1hZnRlci0tcyB7XG4gIEBpbmNsdWRlIG1lZGlhKFwiPnNtYWxsXCIpIHtcbiAgICBkaXNwbGF5OiBub25lO1xuICB9XG59XG5cbi51LWhpZGUtYWZ0ZXItLW0ge1xuICBAaW5jbHVkZSBtZWRpYShcIj5tZWRpdW1cIikge1xuICAgIGRpc3BsYXk6IG5vbmU7XG4gIH1cbn1cblxuLnUtaGlkZS1hZnRlci0tbCB7XG4gIEBpbmNsdWRlIG1lZGlhKFwiPmxhcmdlXCIpIHtcbiAgICBkaXNwbGF5OiBub25lO1xuICB9XG59XG5cbi51LWhpZGUtYWZ0ZXItLXhsIHtcbiAgQGluY2x1ZGUgbWVkaWEoXCI+eGxhcmdlXCIpIHtcbiAgICBkaXNwbGF5OiBub25lO1xuICB9XG59XG4iLCIvKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKlxcXG4gICAgJFNQQUNJTkdcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuJHNpemVzOiAoXCJcIjogJHNwYWNlLCAtLXF1YXJ0ZXI6ICRzcGFjZSAvIDQsIC0taGFsZjogJHNwYWNlIC8gMiwgLS1hbmQtaGFsZjogJHNwYWNlICogMS41LCAtLWRvdWJsZTogJHNwYWNlICogMiwgLS10cmlwbGU6ICRzcGFjZSAqIDMsIC0tcXVhZDogJHNwYWNlICogNCwgLS16ZXJvOiAwcmVtKTtcblxuJHNpZGVzOiAoXCJcIjogXCJcIiwgLS10b3A6IFwiLXRvcFwiLCAtLWJvdHRvbTogXCItYm90dG9tXCIsIC0tbGVmdDogXCItbGVmdFwiLCAtLXJpZ2h0OiBcIi1yaWdodFwiKTtcblxuQGVhY2ggJHNpemVfa2V5LCAkc2l6ZV92YWx1ZSBpbiAkc2l6ZXMge1xuICAudS1zcGFjaW5nI3skc2l6ZV9rZXl9IHtcbiAgICAmID4gKiArICoge1xuICAgICAgbWFyZ2luLXRvcDogI3skc2l6ZV92YWx1ZX07XG4gICAgfVxuICB9XG5cbiAgQGVhY2ggJHNpZGVfa2V5LCAkc2lkZV92YWx1ZSBpbiAkc2lkZXMge1xuICAgIC51LXBhZGRpbmcjeyRzaXplX2tleX0jeyRzaWRlX2tleX0ge1xuICAgICAgcGFkZGluZyN7JHNpZGVfdmFsdWV9OiAjeyRzaXplX3ZhbHVlfTtcbiAgICB9XG5cbiAgICAudS1zcGFjZSN7JHNpemVfa2V5fSN7JHNpZGVfa2V5fSB7XG4gICAgICBtYXJnaW4jeyRzaWRlX3ZhbHVlfTogI3skc2l6ZV92YWx1ZX07XG4gICAgfVxuICB9XG59XG5cbi51LXNwYWNpbmctLWxlZnQge1xuICAmID4gKiArICoge1xuICAgIG1hcmdpbi1sZWZ0OiAkc3BhY2U7XG4gIH1cbn1cbiIsIi8qIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqXFxcbiAgICAkSEVMUEVSL1RSVU1QIENMQVNTRVNcblxcKiAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi9cblxuLyoqXG4gKiBDb21wbGV0ZWx5IHJlbW92ZSBmcm9tIHRoZSBmbG93IGJ1dCBsZWF2ZSBhdmFpbGFibGUgdG8gc2NyZWVuIHJlYWRlcnMuXG4gKi9cbi5pcy12aXNoaWRkZW4sXG4udmlzdWFsbHktaGlkZGVuIHtcbiAgcG9zaXRpb246IGFic29sdXRlICFpbXBvcnRhbnQ7XG4gIG92ZXJmbG93OiBoaWRkZW47XG4gIHdpZHRoOiAxcHg7XG4gIGhlaWdodDogMXB4O1xuICBwYWRkaW5nOiAwO1xuICBib3JkZXI6IDA7XG4gIGNsaXA6IHJlY3QoMXB4LCAxcHgsIDFweCwgMXB4KTtcbn1cblxuLyoqXG4gKiBIaWRlIGVsZW1lbnRzIG9ubHkgcHJlc2VudCBhbmQgbmVjZXNzYXJ5IGZvciBqcyBlbmFibGVkIGJyb3dzZXJzLlxuICovXG4ubm8tanMgLm5vLWpzLWhpZGUge1xuICBkaXNwbGF5OiBub25lO1xufVxuXG4udS1mdWxsLXdpZHRoIHtcbiAgd2lkdGg6IDEwMCU7XG59XG5cbi51LWFsaWduLWNlbnRlciB7XG4gIHRleHQtYWxpZ246IGNlbnRlcjtcbn1cblxuLnUtcmV2ZXJzZWQtb3V0IHtcbiAgY29sb3I6ICRjLXdoaXRlO1xuXG4gIHAsXG4gIGgxIGEsXG4gIGgyIGEsXG4gIGgzIGEge1xuICAgIGNvbG9yOiAkYy13aGl0ZTtcbiAgfVxufVxuXG4vKipcbiAqIFJlbW92ZSBhbGwgbWFyZ2lucy9wYWRkaW5nXG4gKi9cbi51LW5vLXNwYWNpbmcge1xuICBwYWRkaW5nOiAwO1xuICBtYXJnaW46IDA7XG59XG5cbi8qKlxuICogQWN0aXZlIG9uL29mZiBzdGF0ZXNcbiAqL1xuLnUtYWN0aXZlLS1vZmYge1xuICBkaXNwbGF5OiBub25lO1xufVxuXG5bY2xhc3MqPVwiLWlzLWFjdGl2ZVwiXS5qcy10b2dnbGUtcGFyZW50LFxuW2NsYXNzKj1cIi1pcy1hY3RpdmVcIl0uanMtdG9nZ2xlIHtcbiAgLnUtYWN0aXZlLS1vbiB7XG4gICAgZGlzcGxheTogbm9uZTtcbiAgfVxuXG4gIC51LWFjdGl2ZS0tb2ZmIHtcbiAgICBkaXNwbGF5OiBibG9jaztcbiAgfVxufVxuXG5bY2xhc3MqPVwiLWlzLWFjdGl2ZVwiXSB7XG4gIC51LWhpZGUtb24tYWN0aXZlIHtcbiAgICBkaXNwbGF5OiBub25lO1xuICB9XG59XG5cbi8qKlxuICogQnJlYWtvdXQgY29udGVudFxuICovXG4udS1icmVha291dCB7XG4gIG1hcmdpbi1yaWdodDogLSRzcGFjZTtcbiAgbWFyZ2luLWxlZnQ6IC0kc3BhY2U7XG5cbiAgQGluY2x1ZGUgbWVkaWEoXCI+eHhsYXJnZVwiKSB7XG4gICAgbWFyZ2luLWxlZnQ6IC0kc3BhY2UtZG91YmxlO1xuICAgIG1hcmdpbi1yaWdodDogLSRzcGFjZS1kb3VibGU7XG4gIH1cbn1cblxuLyoqXG4gKiBKdXN0aWZ5IGxlZnQvcmlnaHQgY29udGVudFxuICovXG4udS1zcGxpdC1jb250ZW50IHtcbiAgZGlzcGxheTogZmxleDtcblxuICBAaW5jbHVkZSBtZWRpYShcIjw9eHNtYWxsXCIpIHtcbiAgICBmbGV4LXdyYXA6IHdyYXA7XG4gICAgZmxleC1kaXJlY3Rpb246IGNvbHVtbi1yZXZlcnNlO1xuICAgIGp1c3RpZnktY29udGVudDogZmxleC1lbmQ7XG5cbiAgICA+ICogKyAqIHtcbiAgICAgIG1hcmdpbi1ib3R0b206ICRzcGFjZTtcbiAgICB9XG4gIH1cblxuXG4gIEBpbmNsdWRlIG1lZGlhKFwiPnhzbWFsbFwiKSB7XG4gICAganVzdGlmeS1jb250ZW50OiBzcGFjZS1iZXR3ZWVuO1xuICAgIGFsaWduLWl0ZW1zOiBmbGV4LXN0YXJ0O1xuICB9XG59XG4iXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IjtBQUFBOzs7Ozs7Ozs7Ozs7Ozs7Ozs7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 */","/**\n * CONTENTS\n *\n * SETTINGS\n * Variables............Globally-available variables and config.\n * Z-Index..............Semantic z-index manifest\n *\n * TOOLS\n * Mixins...............Useful mixins.\n * Include Media........Sass library for writing CSS media queries.\n * Media Query Test.....Displays the current breakport you're in.\n *\n * GENERIC\n * Reset................A level playing field.\n *\n * BASE\n * Forms................Common and default form styles.\n * Headings.............H1âH6 styles.\n * Links................Link styles.\n * Lists................Default list styles.\n * Main.................Page body defaults.\n * Media................Image and video styles.\n * Tables...............Default table styles.\n * Text.................Default text styles.\n *\n * LAYOUT\n * Grids................Grid/column classes.\n * Wrappers.............Wrapping/constraining elements.\n *\n * COMPONENTS\n * Blocks...............Modular components often consisting of text and media.\n * Cards................Modular components for mainly text and data (card-like).\n * Heros................Leading hero image/caption section of a content type.\n * Sections.............Larger components of pages.\n * Forms................Specific form styling.\n *\n * OBJECTS\n * Buttons..............Various button styles and styles.\n * Icons................Icon styles and settings.\n * Lists................Various site list styles.\n * Navs.................Site navigations.\n * Media................Specific media objects, e.g. figures\n *\n * TEXT\n * Text.................Various text-specific class definitions.\n *\n * PAGE STRUCTURE\n * Article..............Post-type pages with styled text.\n * Gallery..............Styles for all things gallery.\n * Footer...............The main page footer.\n * Header...............The main page header.\n * Main.................Content area styles.\n *\n * MODIFIERS\n * Animations...........Animation and transition effects.\n * Colors...............Text and background colors.\n * Display..............Show and hide and breakpoint visibility rules.\n * Spacings.............Padding and margins in classes.\n *\n * TRUMPS\n * Helper Classes.......Helper classes loaded last in the cascade.\n */\n\n/* ------------------------------------ *\\\n    $SETTINGS\n\\* ------------------------------------ */\n@import \"settings.variables\";\n@import \"settings.z-index\";\n\n/* ------------------------------------ *\\\n    $TOOLS\n\\* ------------------------------------ */\n@import \"tools.mixins\";\n@import \"tools.include-media\";\n\n$tests: false;\n\n@import \"tools.mq-tests\";\n\n/* ------------------------------------ *\\\n    $GENERIC\n\\* ------------------------------------ */\n@import \"generic.reset\";\n\n/* ------------------------------------ *\\\n    $BASE\n\\* ------------------------------------ */\n@import \"base.fonts\";\n@import \"base.forms\";\n@import \"base.headings\";\n@import \"base.links\";\n@import \"base.lists\";\n@import \"base.main\";\n@import \"base.media\";\n@import \"base.tables\";\n@import \"base.text\";\n\n/* ------------------------------------ *\\\n    $LAYOUT\n\\* ------------------------------------ */\n@import \"layout.grids\";\n@import \"layout.wrappers\";\n\n/* ------------------------------------ *\\\n    $TEXT\n\\* ------------------------------------ */\n@import \"objects.text\";\n@import \"objects.buttons\";\n\n/* ------------------------------------ *\\\n    $COMPONENTS\n\\* ------------------------------------ */\n@import \"components.blocks\";\n@import \"components.cards\";\n@import \"components.heros\";\n@import \"components.sections\";\n@import \"components.forms\";\n\n/* ------------------------------------ *\\\n    $OBJECTS\n\\* ------------------------------------ */\n@import \"objects.icons\";\n@import \"objects.lists\";\n@import \"objects.navs\";\n@import \"objects.media\";\n\n/* ------------------------------------ *\\\n    $PAGE STRUCTURE\n\\* ------------------------------------ */\n@import \"module.article\";\n@import \"module.gallery\";\n@import \"module.footer\";\n@import \"module.header\";\n@import \"module.main\";\n\n/* ------------------------------------ *\\\n    $MODIFIERS\n\\* ------------------------------------ */\n@import \"modifier.animations\";\n@import \"modifier.colors\";\n@import \"modifier.display\";\n@import \"modifier.spacing\";\n\n/* ------------------------------------ *\\\n    $VENDORS\n\\* ------------------------------------ */\n\n/* ------------------------------------ *\\\n    $TRUMPS\n\\* ------------------------------------ */\n@import \"trumps.helper-classes\";\n","/* ------------------------------------ *\\\n    $VARIABLES\n\\* ------------------------------------ */\n\n/**\n * Grid & Baseline Setup\n */\n// Global\n$max-width: 1200px;\n$max-width-xl: 1600px;\n\n// Grid\n$grid-columns: 16;\n$col-width: 65;\n$gutter: 20;\n\n/**\n * Theme Colors\n */\n\n// Neutrals\n$c-white: #fff;\n$c-gray--light: #f0f0f0;\n$c-gray: #666;\n$c-gray--dark: #c0c1c5;\n$c-black: #000;\n\n$c-primary: #fbc617;\n$c-secondary: #ef4438;\n$c-tertiary: #d1d628;\n\n/**\n * Default Colors\n */\n$c-error: #f00;\n$c-valid: #089e00;\n$c-warning: #fff664;\n$c-information: #000db5;\n$c-overlay: rgba($c-black, 0.8);\n\n/**\n * Style Colors\n */\n$c-body-color: $c-black;\n$c-link-color: $c-secondary;\n$c-link-hover-color: darker($c-secondary, 10%);\n$c-button-primary: $c-secondary;\n$c-button-primary-hover: darker($c-secondary, 10%);\n$c-button-secondary: $c-tertiary;\n$c-button-secondary-hover: darker($c-tertiary, 10%);\n$c-border: $c-gray;\n\n/**\n * Typography\n */\n$ff-font: \"Roboto\", sans-serif;\n$ff-font--sans: $ff-font;\n$ff-font--serif: serif;\n$ff-font--monospace: Menlo, Monaco, \"Courier New\", \"Courier\", monospace;\n\n// Theme typefaces\n$ff-font--primary: \"Go Bold\", sans-serif;\n$ff-font--secondary: \"Big John\", sans-serif;\n\n/**\n * Icons\n */\n$icon-xsmall: 15px;\n$icon-small: 20px;\n$icon-medium: 30px;\n$icon-large: 40px;\n$icon-xlarge: 70px;\n\n/**\n * Common Breakpoints\n */\n$xsmall: 400px;\n$small: 550px;\n$medium: 700px;\n$large: 850px;\n$xlarge: 1000px;\n$xxlarge: 1200px;\n$xxxlarge: 1400px;\n\n$breakpoints: (\"xsmall\": $xsmall, \"small\": $small, \"medium\": $medium, \"large\": $large, \"xlarge\": $xlarge, \"xxlarge\": $xxlarge, \"xxxlarge\": $xxxlarge);\n\n/**\n * Animation\n */\n$hard-ease-in: cubic-bezier(0.86, 0, 0.07, 1);\n$transition-all: all 0.23s $hard-ease-in;\n\n/**\n * Border Styles\n */\n$border-radius-curve: 2px;\n$border-radius-curve--hard: 4px;\n$border--standard: 1px solid $c-border;\n$border--standard-light: 2px solid $c-gray--light;\n$box-shadow--standard: 0px 4px 12px rgba($c-black, 0.05);\n$box-shadow--thick: 0px 8px 24px rgba($c-black, 0.2);\n\n/**\n * Default Spacing/Padding\n * Maintain a spacing system divisible by 10\n */\n$space: 20px;\n$space-quarter: $space / 4;\n$space-half: $space / 2;\n$space-and-half: $space * 1.5;\n$space-double: $space * 2;\n$space-double-half: $space * 2.5;\n$space-triple: $space * 3;\n$space-quad: $space * 4;\n\n/**\n * Font Sizes\n */\n\n/**\n * Native Custom Properties\n */\n:root {\n  --body-font-size: 16px;\n  --font-size-xs: 13px;\n  --font-size-s: 16px;\n  --font-size-m: 20px;\n  --font-size-l: 22px;\n  --font-size-xl: 28px;\n  --font-size-xxl: 36px;\n}\n\n// Medium Breakpoint\n@media screen and (min-width: 700px) {\n  :root {\n    --font-size-xs: 14px;\n    --font-size-s: 18px;\n    --font-size-m: 22px;\n    --font-size-l: 24px;\n    --font-size-xl: 32px;\n    --font-size-xxl: 40px;\n  }\n}\n\n$body-font-size: var(--body-font-size, 16px);\n$font-size-xs: var(--font-size-xs, 14px);\n$font-size-s: var(--font-size-s, 18px);\n$font-size-m: var(--font-size-m, 22px);\n$font-size-l: var(--font-size-l, 24px);\n$font-size-xl: var(--font-size-xl, 32px);\n$font-size-xxl: var(--font-size-xxl, 40px);\n","/* ------------------------------------ *\\\n    $MIXINS\n\\* ------------------------------------ */\n\n/**\n * Standard paragraph\n */\n@mixin p {\n  line-height: 1.5;\n  font-family: $ff-font;\n  font-size: $body-font-size;\n\n  @media print {\n    font-size: 12px;\n    line-height: 1.3;\n  }\n}\n\n/**\n * String interpolation function for SASS variables in SVG Image URI's\n */\n@function url-friendly-color($color) {\n  @return \"%23\" + str-slice(\"#{$color}\", 2, -1);\n}\n","/* ------------------------------------ *\\\n    $MEDIA QUERY TESTS\n\\* ------------------------------------ */\n\n@if $tests == true {\n  body {\n    &::before {\n      display: block;\n      position: fixed;\n      z-index: $z-index-mq-display;\n      background: black;\n      bottom: 0;\n      right: 0;\n      padding: 0.5em 1em;\n      content: 'No Media Query';\n      color: transparentize(#fff, 0.25);\n      border-top-left-radius: 10px;\n      font-size: 12 / 16 + em;\n\n      @media print {\n        display: none;\n      }\n    }\n\n    &::after {\n      display: block;\n      position: fixed;\n      height: 5px;\n      bottom: 0;\n      left: 0;\n      right: 0;\n      z-index: $z-index-mq-display;\n      content: '';\n      background: black;\n\n      @media print {\n        display: none;\n      }\n    }\n\n    @include media(\">xsmall\") {\n      &::before {\n        content: \"xsmall: #{$xsmall}\";\n      }\n\n      &::after,\n      &::before {\n        background: dodgerblue;\n      }\n    }\n\n\n    @include media(\">small\") {\n      &::before {\n        content: \"small: #{$small}\";\n      }\n\n      &::after,\n      &::before {\n        background: darkseagreen;\n      }\n    }\n\n\n    @include media(\">medium\") {\n      &::before {\n        content: \"medium: #{$medium}\";\n      }\n\n      &::after,\n      &::before {\n        background: lightcoral;\n      }\n    }\n\n\n    @include media(\">large\") {\n      &::before {\n        content: \"large: #{$large}\";\n      }\n\n      &::after,\n      &::before {\n        background: mediumvioletred;\n      }\n    }\n\n\n    @include media(\">xlarge\") {\n      &::before {\n        content: \"xlarge: #{$xlarge}\";\n      }\n\n      &::after,\n      &::before {\n        background: hotpink;\n      }\n    }\n\n\n    @include media(\">xxlarge\") {\n      &::before {\n        content: \"xxlarge: #{$xxlarge}\";\n      }\n\n      &::after,\n      &::before {\n        background: orangered;\n      }\n    }\n\n\n    @include media(\">xxxlarge\") {\n      &::before {\n        content: \"xxxlarge: #{$xxxlarge}\";\n      }\n\n      &::after,\n      &::before {\n        background: dodgerblue;\n      }\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $RESET\n\\* ------------------------------------ */\n\n/* Border-Box http:/paulirish.com/2012/box-sizing-border-box-ftw/ */\n*,\n*::before,\n*::after {\n  box-sizing: border-box;\n}\n\nbody {\n  margin: 0;\n  padding: 0;\n}\n\nblockquote,\nbody,\ndiv,\nfigure,\nfooter,\nform,\nh1,\nh2,\nh3,\nh4,\nh5,\nh6,\nheader,\nhtml,\niframe,\nlabel,\nlegend,\nli,\nnav,\nobject,\nol,\np,\nsection,\ntable,\nul {\n  margin: 0;\n  padding: 0;\n}\n\narticle,\nfigure,\nfooter,\nheader,\nhgroup,\nnav,\nsection {\n  display: block;\n}\n\naddress {\n  font-style: normal;\n}\n","/* ------------------------------------ *\\\n    $FONTS\n\\* ------------------------------------ */\n@import url(\"https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,700;1,400;1,700&display=swap\");\n\n@font-face {\n  font-family: 'Big John';\n  src: url(\"../fonts/big_john-webfont.woff2\") format(\"woff2\"), url(\"../fonts/big_john-webfont.woff\") format(\"woff\");\n  font-weight: normal;\n  font-style: normal;\n}\n\n@font-face {\n  font-family: 'Go Bold';\n  src: url(\"../fonts/gobold_regular_italic-webfont.woff2\") format(\"woff2\"), url(\"../fonts/gobold_regular_italic-webfont.woff\") format(\"woff\");\n  font-weight: normal;\n  font-style: normal;\n}\n","/* ------------------------------------ *\\\n    $FORMS\n\\* ------------------------------------ */\n\nform ol,\nform ul {\n  list-style: none;\n  margin-left: 0;\n}\n\nlegend {\n  margin-bottom: 6px;\n  font-weight: bold;\n}\n\nfieldset {\n  border: 0;\n  padding: 0;\n  margin: 0;\n  min-width: 0;\n}\n\ninput,\nselect,\ntextarea {\n  width: 100%;\n  border: none;\n  appearance: none;\n}\n\ninput[type=text],\ninput[type=password],\ninput[type=email],\ninput[type=search],\ninput[type=tel],\nselect {\n  font-size: 16px;\n  font-family: $ff-font;\n  padding: 18px $space;\n  box-shadow: none;\n  border: $border--standard;\n\n  &::placeholder {\n    color: $c-gray;\n  }\n\n  &:focus {\n    outline: 2px solid $c-border;\n  }\n}\n\ninput[type=radio],\ninput[type=checkbox] {\n  outline: none;\n  margin: 0;\n  margin-right: $space-quarter;\n  height: 18px;\n  width: 18px;\n  line-height: 1;\n  background-size: 18px;\n  background-repeat: no-repeat;\n  background-position: 0 0;\n  cursor: pointer;\n  display: block;\n  float: left;\n  border: $border--standard;\n  padding: 0;\n  user-select: none;\n  appearance: none;\n  background-color: $c-white;\n}\n\ninput[type=radio] + label,\ninput[type=checkbox] + label {\n  display: inline-block;\n  cursor: pointer;\n  position: relative;\n  margin-bottom: 0;\n}\n\ninput[type=radio]:checked,\ninput[type=checkbox]:checked {\n  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3E%3Cpath d='M26.08,3.56l-2,1.95L10.61,19l-5-4L3.47,13.29,0,17.62l2.17,1.73L9.1,24.9,11,26.44l1.77-1.76L28.05,9.43,30,7.48Z' fill='#{url-friendly-color($c-secondary)}'/%3E%3C/svg%3E\");\n  background-repeat: no-repeat;\n  background-position: center center;\n  background-size: 10px;\n}\n\ninput[type=radio] {\n  border-radius: 50px;\n}\n\ninput[type=checkbox] {\n  border-radius: $border-radius-curve;\n}\n\ninput[type=submit] {\n  transition: $transition-all;\n}\n\n/* clears the 'X' from Internet Explorer */\ninput[type=search]::-ms-clear {\n  display: none;\n  width: 0;\n  height: 0;\n}\n\ninput[type=search]::-ms-reveal {\n  display: none;\n  width: 0;\n  height: 0;\n}\n\n/* clears the 'X' from Chrome */\ninput[type=\"search\"]::-webkit-search-decoration,\ninput[type=\"search\"]::-webkit-search-cancel-button,\ninput[type=\"search\"]::-webkit-search-results-button,\ninput[type=\"search\"]::-webkit-search-results-decoration {\n  display: none;\n}\n\n/* removes the blue background on Chrome's autocomplete */\ninput:-webkit-autofill,\ninput:-webkit-autofill:hover,\ninput:-webkit-autofill:focus,\ninput:-webkit-autofill:active {\n  -webkit-box-shadow: 0 0 0 30px white inset;\n}\n\nselect {\n  background-color: $c-white;\n  appearance: none;\n  position: relative;\n  width: 100%;\n  padding-right: $space-and-half;\n}\n","/* ------------------------------------ *\\\n    $HEADINGS\n\\* ------------------------------------ */\n\n@mixin o-heading--xxl {\n  font-family: $ff-font--primary;\n  font-size: $font-size-xxl;\n  line-height: 1.3;\n}\n\nh1,\n.o-heading--xxl {\n  @include o-heading--xxl;\n}\n\n@mixin o-heading--xl {\n  font-family: $ff-font--primary;\n  font-size: $font-size-xl;\n  line-height: 1.3;\n}\n\nh2,\n.o-heading--xl {\n  @include o-heading--xl;\n}\n\n@mixin o-heading--l {\n  font-family: $ff-font--primary;\n  font-size: $font-size-l;\n  line-height: 1.4;\n}\n\nh3,\n.o-heading--l {\n  @include o-heading--l;\n}\n\n@mixin o-heading--m {\n  font-family: $ff-font--primary;\n  font-size: $font-size-m;\n  line-height: 1.4;\n}\n\nh4,\n.o-heading--m {\n  @include o-heading--m;\n}\n\n@mixin o-heading--s {\n  font-family: $ff-font--primary;\n  font-size: $font-size-s;\n  line-height: 1.6;\n}\n\nh5,\n.o-heading--s {\n  @include o-heading--s;\n}\n\n@mixin o-heading--xs {\n  font-family: $ff-font--primary;\n  font-size: $font-size-xs;\n  line-height: 1.5;\n}\n\nh6,\n.o-heading--xs {\n  @include o-heading--xs;\n}\n","/* ------------------------------------ *\\\n    $LINKS\n\\* ------------------------------------ */\n\na {\n  text-decoration: none;\n  color: $c-link-color;\n  transition: $transition-all;\n\n  &:hover {\n    color: $c-link-hover-color;\n  }\n}\n","/* ------------------------------------ *\\\n    $LISTS\n\\* ------------------------------------ */\n\nol,\nul {\n  margin: 0;\n  padding: 0;\n  list-style: none;\n}\n\n/**\n * Definition Lists\n */\ndl {\n  overflow: hidden;\n  margin: 0 0 $space;\n}\n\ndt {\n  font-weight: bold;\n}\n\ndd {\n  margin-left: 0;\n}\n","/* ------------------------------------ *\\\n    $SITE MAIN\n\\* ------------------------------------ */\n\nbody {\n  background: $c-white;\n  font: 400 16px / 1.3 $ff-font;\n  -webkit-text-size-adjust: 100%;\n  color: $c-body-color;\n  -webkit-font-smoothing: antialiased;\n  -moz-osx-font-smoothing: grayscale;\n}\n","/* ------------------------------------ *\\\n    $MEDIA ELEMENTS\n\\* ------------------------------------ */\n\n/**\n * Flexible Media\n */\nimg,\nvideo,\nobject,\nsvg,\niframe {\n  max-width: 100%;\n  border: none;\n  display: block;\n}\n\nimg {\n  height: auto;\n}\n\nsvg {\n  max-height: 100%;\n}\n\npicture,\npicture img {\n  display: block;\n}\n\nfigure {\n  position: relative;\n  display: inline-block;\n  overflow: hidden;\n}\n\nfigcaption {\n  a {\n    display: block;\n  }\n}\n\n/* ------------------------------------ *\\\n    $PRINT STYLES\n\\* ------------------------------------ */\n\n@media print {\n  *,\n  *::before,\n  *::after,\n  *::first-letter,\n  *::first-line {\n    background: transparent !important;\n    color: black !important;\n    box-shadow: none !important;\n    text-shadow: none !important;\n  }\n\n  a,\n  a:visited {\n    text-decoration: underline;\n  }\n\n  a[href]::after {\n    content: \" (\" attr(href) \")\";\n  }\n\n  abbr[title]::after {\n    content: \" (\" attr(title) \")\";\n  }\n\n  /*\n   * Don't show links that are fragment identifiers,\n   * or use the `javascript:` pseudo protocol\n   */\n  a[href^=\"#\"]::after,\n  a[href^=\"javascript:\"]::after {\n    content: \"\";\n  }\n\n  pre,\n  blockquote {\n    border: 1px solid #999;\n    page-break-inside: avoid;\n  }\n\n  /*\n   * Printing Tables:\n   * http://css-discuss.incutio.com/wiki/Printing_Tables\n   */\n  thead {\n    display: table-header-group;\n  }\n\n  tr,\n  img {\n    page-break-inside: avoid;\n  }\n\n  img {\n    max-width: 100% !important;\n    height: auto;\n  }\n\n  p,\n  h2,\n  h3 {\n    orphans: 3;\n    widows: 3;\n  }\n\n  h2,\n  h3 {\n    page-break-after: avoid;\n  }\n\n  .no-print,\n  .c-main-header,\n  .c-main-footer,\n  .ad {\n    display: none;\n  }\n}\n","/* ------------------------------------ *\\\n    $TABLES\n\\* ------------------------------------ */\n\ntable {\n  border-collapse: collapse;\n  border-spacing: 0;\n  border: 1px solid $c-gray;\n  width: 100%;\n}\n\nth {\n  text-align: left;\n  border: 1px solid transparent;\n  padding: ($space / 2) 0;\n  text-transform: uppercase;\n  vertical-align: top;\n  font-weight: bold;\n}\n\ntr {\n  border: 1px solid transparent;\n}\n\ntd {\n  border: 1px solid transparent;\n  padding: $space / 2;\n}\n\n/**\n * Responsive Table\n */\n.c-table--responsive {\n  border: 1px solid $c-gray;\n  border-collapse: collapse;\n  padding: 0;\n  width: 100%;\n\n  tr {\n    border: 1px solid $c-gray;\n    background-color: $c-gray--light;\n  }\n\n  th,\n  td {\n    padding: $space / 2;\n  }\n\n  th {\n    font-size: $font-size-xs;\n    text-transform: uppercase;\n    border-bottom: 1px solid $c-gray;\n  }\n\n  @include media(\"<=medium\") {\n    border: 0;\n\n    thead {\n      border: none;\n      clip: rect(0 0 0 0);\n      height: 1px;\n      margin: -1px;\n      overflow: hidden;\n      padding: 0;\n      position: absolute;\n      width: 1px;\n    }\n\n    tr {\n      border-bottom: 3px solid $c-gray;\n      display: block;\n      margin-bottom: $space / 2;\n\n      &.this-is-active {\n        td:not(:first-child) {\n          display: flex;\n        }\n\n        td:first-child::before {\n          content: \"- \" attr(data-label);\n        }\n      }\n    }\n\n    td {\n      border-bottom: 1px solid $c-gray;\n      display: flex;\n      align-items: center;\n      justify-content: space-between;\n      min-height: 40px;\n\n      &:first-child {\n        cursor: pointer;\n\n        &::before {\n          content: \"+ \" attr(data-label);\n        }\n      }\n\n      &:last-child {\n        border-bottom: 0;\n      }\n\n      &:not(:first-child) {\n        display: none;\n      }\n\n      &::before {\n        content: attr(data-label);\n        font-weight: bold;\n        text-transform: uppercase;\n        font-size: $font-size-xs;\n      }\n    }\n  }\n}\n","@charset \"UTF-8\";\n\n//     _            _           _                           _ _\n//    (_)          | |         | |                         | (_)\n//     _ _ __   ___| |_   _  __| | ___   _ __ ___   ___  __| |_  __ _\n//    | | '_ \\ / __| | | | |/ _` |/ _ \\ | '_ ` _ \\ / _ \\/ _` | |/ _` |\n//    | | | | | (__| | |_| | (_| |  __/ | | | | | |  __/ (_| | | (_| |\n//    |_|_| |_|\\___|_|\\__,_|\\__,_|\\___| |_| |_| |_|\\___|\\__,_|_|\\__,_|\n//\n//      Simple, elegant and maintainable media queries in Sass\n//                        v1.4.9\n//\n//                http://include-media.com\n//\n//         Authors: Eduardo Boucas (@eduardoboucas)\n//                  Hugo Giraudel (@hugogiraudel)\n//\n//      This project is licensed under the terms of the MIT license\n\n////\n/// include-media library public configuration\n/// @author Eduardo Boucas\n/// @access public\n////\n\n///\n/// Creates a list of global breakpoints\n///\n/// @example scss - Creates a single breakpoint with the label `phone`\n///  $breakpoints: ('phone': 320px);\n///\n$breakpoints: (\n  'phone': 320px,\n  'tablet': 768px,\n  'desktop': 1024px\n) !default;\n\n///\n/// Creates a list of static expressions or media types\n///\n/// @example scss - Creates a single media type (screen)\n///  $media-expressions: ('screen': 'screen');\n///\n/// @example scss - Creates a static expression with logical disjunction (OR operator)\n///  $media-expressions: (\n///    'retina2x': '(-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi)'\n///  );\n///\n$media-expressions: (\n  'screen': 'screen',\n  'print': 'print',\n  'handheld': 'handheld',\n  'landscape': '(orientation: landscape)',\n  'portrait': '(orientation: portrait)',\n  'retina2x': '(-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi), (min-resolution: 2dppx)',\n  'retina3x': '(-webkit-min-device-pixel-ratio: 3), (min-resolution: 350dpi), (min-resolution: 3dppx)'\n) !default;\n\n///\n/// Defines a number to be added or subtracted from each unit when declaring breakpoints with exclusive intervals\n///\n/// @example scss - Interval for pixels is defined as `1` by default\n///  @include media('>128px') {}\n///\n///  /* Generates: */\n///  @media(min-width: 129px) {}\n///\n/// @example scss - Interval for ems is defined as `0.01` by default\n///  @include media('>20em') {}\n///\n///  /* Generates: */\n///  @media(min-width: 20.01em) {}\n///\n/// @example scss - Interval for rems is defined as `0.1` by default, to be used with `font-size: 62.5%;`\n///  @include media('>2.0rem') {}\n///\n///  /* Generates: */\n///  @media(min-width: 2.1rem) {}\n///\n$unit-intervals: (\n  'px': 1,\n  'em': 0.01,\n  'rem': 0.1,\n  '': 0\n) !default;\n\n///\n/// Defines whether support for media queries is available, useful for creating separate stylesheets\n/// for browsers that don't support media queries.\n///\n/// @example scss - Disables support for media queries\n///  $im-media-support: false;\n///  @include media('>=tablet') {\n///    .foo {\n///      color: tomato;\n///    }\n///  }\n///\n///  /* Generates: */\n///  .foo {\n///    color: tomato;\n///  }\n///\n$im-media-support: true !default;\n\n///\n/// Selects which breakpoint to emulate when support for media queries is disabled. Media queries that start at or\n/// intercept the breakpoint will be displayed, any others will be ignored.\n///\n/// @example scss - This media query will show because it intercepts the static breakpoint\n///  $im-media-support: false;\n///  $im-no-media-breakpoint: 'desktop';\n///  @include media('>=tablet') {\n///    .foo {\n///      color: tomato;\n///    }\n///  }\n///\n///  /* Generates: */\n///  .foo {\n///    color: tomato;\n///  }\n///\n/// @example scss - This media query will NOT show because it does not intercept the desktop breakpoint\n///  $im-media-support: false;\n///  $im-no-media-breakpoint: 'tablet';\n///  @include media('>=desktop') {\n///    .foo {\n///      color: tomato;\n///    }\n///  }\n///\n///  /* No output */\n///\n$im-no-media-breakpoint: 'desktop' !default;\n\n///\n/// Selects which media expressions are allowed in an expression for it to be used when media queries\n/// are not supported.\n///\n/// @example scss - This media query will show because it intercepts the static breakpoint and contains only accepted media expressions\n///  $im-media-support: false;\n///  $im-no-media-breakpoint: 'desktop';\n///  $im-no-media-expressions: ('screen');\n///  @include media('>=tablet', 'screen') {\n///    .foo {\n///      color: tomato;\n///    }\n///  }\n///\n///   /* Generates: */\n///   .foo {\n///     color: tomato;\n///   }\n///\n/// @example scss - This media query will NOT show because it intercepts the static breakpoint but contains a media expression that is not accepted\n///  $im-media-support: false;\n///  $im-no-media-breakpoint: 'desktop';\n///  $im-no-media-expressions: ('screen');\n///  @include media('>=tablet', 'retina2x') {\n///    .foo {\n///      color: tomato;\n///    }\n///  }\n///\n///  /* No output */\n///\n$im-no-media-expressions: ('screen', 'portrait', 'landscape') !default;\n\n////\n/// Cross-engine logging engine\n/// @author Hugo Giraudel\n/// @access private\n////\n\n\n///\n/// Log a message either with `@error` if supported\n/// else with `@warn`, using `feature-exists('at-error')`\n/// to detect support.\n///\n/// @param {String} $message - Message to log\n///\n@function im-log($message) {\n  @if feature-exists('at-error') {\n    @error $message;\n  }\n\n  @else {\n    @warn $message;\n    $_: noop();\n  }\n\n  @return $message;\n}\n\n///\n/// Determines whether a list of conditions is intercepted by the static breakpoint.\n///\n/// @param {Arglist}   $conditions  - Media query conditions\n///\n/// @return {Boolean} - Returns true if the conditions are intercepted by the static breakpoint\n///\n@function im-intercepts-static-breakpoint($conditions...) {\n  $no-media-breakpoint-value: map-get($breakpoints, $im-no-media-breakpoint);\n\n  @each $condition in $conditions {\n    @if not map-has-key($media-expressions, $condition) {\n      $operator: get-expression-operator($condition);\n      $prefix: get-expression-prefix($operator);\n      $value: get-expression-value($condition, $operator);\n\n      @if ($prefix == 'max' and $value <= $no-media-breakpoint-value) or ($prefix == 'min' and $value > $no-media-breakpoint-value) {\n        @return false;\n      }\n    }\n\n    @else if not index($im-no-media-expressions, $condition) {\n      @return false;\n    }\n  }\n\n  @return true;\n}\n\n////\n/// Parsing engine\n/// @author Hugo Giraudel\n/// @access private\n////\n\n///\n/// Get operator of an expression\n///\n/// @param {String} $expression - Expression to extract operator from\n///\n/// @return {String} - Any of `>=`, `>`, `<=`, `<`, `â¥`, `â¤`\n///\n@function get-expression-operator($expression) {\n  @each $operator in ('>=', '>', '<=', '<', 'â¥', 'â¤') {\n    @if str-index($expression, $operator) {\n      @return $operator;\n    }\n  }\n\n  // It is not possible to include a mixin inside a function, so we have to\n  // rely on the `im-log(..)` function rather than the `log(..)` mixin. Because\n  // functions cannot be called anywhere in Sass, we need to hack the call in\n  // a dummy variable, such as `$_`. If anybody ever raise a scoping issue with\n  // Sass 3.3, change this line in `@if im-log(..) {}` instead.\n  $_: im-log('No operator found in `#{$expression}`.');\n}\n\n///\n/// Get dimension of an expression, based on a found operator\n///\n/// @param {String} $expression - Expression to extract dimension from\n/// @param {String} $operator - Operator from `$expression`\n///\n/// @return {String} - `width` or `height` (or potentially anything else)\n///\n@function get-expression-dimension($expression, $operator) {\n  $operator-index: str-index($expression, $operator);\n  $parsed-dimension: str-slice($expression, 0, $operator-index - 1);\n  $dimension: 'width';\n\n  @if str-length($parsed-dimension) > 0 {\n    $dimension: $parsed-dimension;\n  }\n\n  @return $dimension;\n}\n\n///\n/// Get dimension prefix based on an operator\n///\n/// @param {String} $operator - Operator\n///\n/// @return {String} - `min` or `max`\n///\n@function get-expression-prefix($operator) {\n  @return if(index(('<', '<=', 'â¤'), $operator), 'max', 'min');\n}\n\n///\n/// Get value of an expression, based on a found operator\n///\n/// @param {String} $expression - Expression to extract value from\n/// @param {String} $operator - Operator from `$expression`\n///\n/// @return {Number} - A numeric value\n///\n@function get-expression-value($expression, $operator) {\n  $operator-index: str-index($expression, $operator);\n  $value: str-slice($expression, $operator-index + str-length($operator));\n\n  @if map-has-key($breakpoints, $value) {\n    $value: map-get($breakpoints, $value);\n  }\n\n  @else {\n    $value: to-number($value);\n  }\n\n  $interval: map-get($unit-intervals, unit($value));\n\n  @if not $interval {\n    // It is not possible to include a mixin inside a function, so we have to\n    // rely on the `im-log(..)` function rather than the `log(..)` mixin. Because\n    // functions cannot be called anywhere in Sass, we need to hack the call in\n    // a dummy variable, such as `$_`. If anybody ever raise a scoping issue with\n    // Sass 3.3, change this line in `@if im-log(..) {}` instead.\n    $_: im-log('Unknown unit `#{unit($value)}`.');\n  }\n\n  @if $operator == '>' {\n    $value: $value + $interval;\n  }\n\n  @else if $operator == '<' {\n    $value: $value - $interval;\n  }\n\n  @return $value;\n}\n\n///\n/// Parse an expression to return a valid media-query expression\n///\n/// @param {String} $expression - Expression to parse\n///\n/// @return {String} - Valid media query\n///\n@function parse-expression($expression) {\n  // If it is part of $media-expressions, it has no operator\n  // then there is no need to go any further, just return the value\n  @if map-has-key($media-expressions, $expression) {\n    @return map-get($media-expressions, $expression);\n  }\n\n  $operator: get-expression-operator($expression);\n  $dimension: get-expression-dimension($expression, $operator);\n  $prefix: get-expression-prefix($operator);\n  $value: get-expression-value($expression, $operator);\n\n  @return '(#{$prefix}-#{$dimension}: #{$value})';\n}\n\n///\n/// Slice `$list` between `$start` and `$end` indexes\n///\n/// @access private\n///\n/// @param {List} $list - List to slice\n/// @param {Number} $start [1] - Start index\n/// @param {Number} $end [length($list)] - End index\n///\n/// @return {List} Sliced list\n///\n@function slice($list, $start: 1, $end: length($list)) {\n  @if length($list) < 1 or $start > $end {\n    @return ();\n  }\n\n  $result: ();\n\n  @for $i from $start through $end {\n    $result: append($result, nth($list, $i));\n  }\n\n  @return $result;\n}\n\n////\n/// String to number converter\n/// @author Hugo Giraudel\n/// @access private\n////\n\n///\n/// Casts a string into a number\n///\n/// @param {String | Number} $value - Value to be parsed\n///\n/// @return {Number}\n///\n@function to-number($value) {\n  @if type-of($value) == 'number' {\n    @return $value;\n  }\n\n  @else if type-of($value) != 'string' {\n    $_: im-log('Value for `to-number` should be a number or a string.');\n  }\n\n  $first-character: str-slice($value, 1, 1);\n  $result: 0;\n  $digits: 0;\n  $minus: ($first-character == '-');\n  $numbers: ('0': 0, '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9);\n\n  // Remove +/- sign if present at first character\n  @if ($first-character == '+' or $first-character == '-') {\n    $value: str-slice($value, 2);\n  }\n\n  @for $i from 1 through str-length($value) {\n    $character: str-slice($value, $i, $i);\n\n    @if not (index(map-keys($numbers), $character) or $character == '.') {\n      @return to-length(if($minus, -$result, $result), str-slice($value, $i));\n    }\n\n    @if $character == '.' {\n      $digits: 1;\n    }\n\n    @else if $digits == 0 {\n      $result: $result * 10 + map-get($numbers, $character);\n    }\n\n    @else {\n      $digits: $digits * 10;\n      $result: $result + map-get($numbers, $character) / $digits;\n    }\n  }\n\n  @return if($minus, -$result, $result);\n}\n\n///\n/// Add `$unit` to `$value`\n///\n/// @param {Number} $value - Value to add unit to\n/// @param {String} $unit - String representation of the unit\n///\n/// @return {Number} - `$value` expressed in `$unit`\n///\n@function to-length($value, $unit) {\n  $units: ('px': 1px, 'cm': 1cm, 'mm': 1mm, '%': 1%, 'ch': 1ch, 'pc': 1pc, 'in': 1in, 'em': 1em, 'rem': 1rem, 'pt': 1pt, 'ex': 1ex, 'vw': 1vw, 'vh': 1vh, 'vmin': 1vmin, 'vmax': 1vmax);\n\n  @if not index(map-keys($units), $unit) {\n    $_: im-log('Invalid unit `#{$unit}`.');\n  }\n\n  @return $value * map-get($units, $unit);\n}\n\n///\n/// This mixin aims at redefining the configuration just for the scope of\n/// the call. It is helpful when having a component needing an extended\n/// configuration such as custom breakpoints (referred to as tweakpoints)\n/// for instance.\n///\n/// @author Hugo Giraudel\n///\n/// @param {Map} $tweakpoints [()] - Map of tweakpoints to be merged with `$breakpoints`\n/// @param {Map} $tweak-media-expressions [()] - Map of tweaked media expressions to be merged with `$media-expression`\n///\n/// @example scss - Extend the global breakpoints with a tweakpoint\n///  @include media-context(('custom': 678px)) {\n///    .foo {\n///      @include media('>phone', '<=custom') {\n///       // ...\n///      }\n///    }\n///  }\n///\n/// @example scss - Extend the global media expressions with a custom one\n///  @include media-context($tweak-media-expressions: ('all': 'all')) {\n///    .foo {\n///      @include media('all', '>phone') {\n///       // ...\n///      }\n///    }\n///  }\n///\n/// @example scss - Extend both configuration maps\n///  @include media-context(('custom': 678px), ('all': 'all')) {\n///    .foo {\n///      @include media('all', '>phone', '<=custom') {\n///       // ...\n///      }\n///    }\n///  }\n///\n@mixin media-context($tweakpoints: (), $tweak-media-expressions: ()) {\n  // Save global configuration\n  $global-breakpoints: $breakpoints;\n  $global-media-expressions: $media-expressions;\n\n  // Update global configuration\n  $breakpoints: map-merge($breakpoints, $tweakpoints) !global;\n  $media-expressions: map-merge($media-expressions, $tweak-media-expressions) !global;\n\n  @content;\n\n  // Restore global configuration\n  $breakpoints: $global-breakpoints !global;\n  $media-expressions: $global-media-expressions !global;\n}\n\n////\n/// include-media public exposed API\n/// @author Eduardo Boucas\n/// @access public\n////\n\n///\n/// Generates a media query based on a list of conditions\n///\n/// @param {Arglist}   $conditions  - Media query conditions\n///\n/// @example scss - With a single set breakpoint\n///  @include media('>phone') { }\n///\n/// @example scss - With two set breakpoints\n///  @include media('>phone', '<=tablet') { }\n///\n/// @example scss - With custom values\n///  @include media('>=358px', '<850px') { }\n///\n/// @example scss - With set breakpoints with custom values\n///  @include media('>desktop', '<=1350px') { }\n///\n/// @example scss - With a static expression\n///  @include media('retina2x') { }\n///\n/// @example scss - Mixing everything\n///  @include media('>=350px', '<tablet', 'retina3x') { }\n///\n@mixin media($conditions...) {\n  @if ($im-media-support and length($conditions) == 0) or (not $im-media-support and im-intercepts-static-breakpoint($conditions...)) {\n    @content;\n  }\n\n  @else if ($im-media-support and length($conditions) > 0) {\n    @media #{unquote(parse-expression(nth($conditions, 1)))} {\n\n      // Recursive call\n      @include media(slice($conditions, 2)...) {\n        @content;\n      }\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $TEXT ELEMENTS\n\\* ------------------------------------ */\n\n/**\n * Text-Related Elements\n */\np {\n  @include p;\n}\n\nsmall {\n  font-size: 90%;\n}\n\n/**\n * Bold\n */\nstrong,\nb {\n  font-weight: bold;\n}\n\n/**\n * Blockquote\n */\nblockquote {\n  display: flex;\n  flex-wrap: wrap;\n\n  &::before {\n    content: \"\\201C\";\n    font-family: $ff-font;\n    font-size: 40px;\n    line-height: 1;\n    color: $c-secondary;\n    min-width: 40px;\n    border-right: 6px solid $c-border;\n    display: block;\n    margin-right: $space;\n  }\n\n  p {\n    line-height: 1.7;\n    flex: 1;\n  }\n}\n\n/**\n * Horizontal Rule\n */\nhr {\n  height: 1px;\n  border: none;\n  background-color: rgba($c-gray--light, 0.5);\n  margin: 0 auto;\n}\n\n/**\n * Abbreviation\n */\nabbr {\n  border-bottom: 1px dotted $c-gray;\n  cursor: help;\n}\n","/* ------------------------------------ *\\\n    $GRIDS\n\\* ------------------------------------ */\n\n.l-grid {\n  display: grid;\n  grid-template-rows: auto;\n  grid-column-gap: $space;\n  grid-row-gap: $space;\n\n  @media all and (-ms-high-contrast: none) {\n    display: flex;\n    flex-direction: row;\n    flex-wrap: wrap;\n    margin-left: -$space;\n    margin-right: -$space;\n\n    > * {\n      margin: $space;\n    }\n  }\n\n  &-item {\n    position: relative;\n  }\n\n  &--large-gutters {\n    grid-column-gap: $space-quad;\n    grid-row-gap: $space-quad;\n  }\n\n  &--2up {\n    @include media(\">small\") {\n      grid-template-columns: repeat(2, 1fr);\n    }\n\n\n    @media all and (-ms-high-contrast: none) {\n      > * {\n        width: calc(50% - #{$space-double});\n      }\n    }\n\n    &--flex {\n      display: flex;\n      flex-wrap: wrap;\n      margin: 0 calc(#{$space} * -1);\n\n      > * {\n        width: 100%;\n        padding-left: $space;\n        padding-right: $space;\n        margin-top: $space * 2;\n\n        @include media(\">small\") {\n          width: 50%;\n        }\n      }\n    }\n  }\n\n  &--3up {\n    @include media(\">small\") {\n      grid-template-columns: repeat(2, 1fr);\n    }\n\n\n    @include media(\">large\") {\n      grid-template-columns: repeat(3, 1fr);\n    }\n\n\n    @media all and (-ms-high-contrast: none) {\n      > * {\n        width: calc(33.333% - #{$space-double});\n      }\n    }\n  }\n\n  &--4up {\n    grid-template-columns: repeat(minmax(200px, 1fr));\n\n    @include media(\">xsmall\") {\n      grid-template-columns: repeat(2, 1fr);\n    }\n\n\n    @include media(\">medium\") {\n      grid-template-columns: repeat(3, 1fr);\n    }\n\n\n    @include media(\">xlarge\") {\n      grid-template-columns: repeat(4, 1fr);\n    }\n\n\n    @media all and (-ms-high-contrast: none) {\n      > * {\n        width: calc(25% - #{$space-double});\n      }\n    }\n  }\n\n  &--4up--at-medium {\n    grid-template-columns: repeat(2, 1fr);\n\n    @include media(\">small\") {\n      grid-template-columns: repeat(3, 1fr);\n    }\n\n\n    @include media(\">medium\") {\n      grid-template-columns: repeat(4, 1fr);\n    }\n\n\n    @media all and (-ms-high-contrast: none) {\n      > * {\n        width: calc(25% - #{$space-double});\n      }\n    }\n  }\n\n  &--5up {\n    grid-row-gap: $space * 2;\n    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));\n\n    @include media(\">large\") {\n      grid-template-columns: repeat(5, 1fr);\n    }\n\n\n    @media all and (-ms-high-contrast: none) {\n      > * {\n        width: calc(20% - #{$space-double});\n      }\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $WRAPPERS & CONTAINERS\n\\* ------------------------------------ */\n\n/**\n * Wrapping element to keep content contained and centered.\n */\n.l-wrap {\n  margin: 0 auto;\n  padding-left: $space;\n  padding-right: $space;\n  width: 100%;\n  position: relative;\n\n  @include media(\">xxlarge\") {\n    padding-left: $space-double;\n    padding-right: $space-double;\n  }\n}\n\n/**\n * Layout containers - keep content centered and within a maximum width. Also\n * adjusts left and right padding as the viewport widens.\n */\n\n.l-container {\n  max-width: $max-width;\n  margin-left: auto;\n  margin-right: auto;\n  position: relative;\n\n  &--xl {\n    max-width: $max-width-xl;\n  }\n}\n\n/**\n * Grid classes\n */\n@for $i from 1 through $grid-columns {\n  .l-container--#{$i}col {\n    @if $i == 1 {\n      max-width: $col-width * $i + $gutter * $i + px;\n    }\n    @else if $i == 2 {\n      max-width: $col-width * $i + $gutter * 1 + px;\n    }\n    @else {\n      max-width: $col-width * $i + $gutter * ($i - 2) + px;\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $TEXT TYPES\n\\* ------------------------------------ */\n\n/**\n * Font Families\n */\n.u-font {\n  font-family: $ff-font;\n}\n\n.u-font--primary,\n.u-font--primary p {\n  font-family: $ff-font--primary;\n}\n\n.u-font--secondary,\n.u-font--secondary p {\n  font-family: $ff-font--secondary;\n}\n\n/**\n * Text Sizes\n */\n\n.u-font--xs {\n  font-size: $font-size-xs;\n}\n\n.u-font--s {\n  font-size: $font-size-s;\n}\n\n.u-font--m {\n  font-size: $font-size-m;\n}\n\n.u-font--l {\n  font-size: $font-size-l;\n}\n\n.u-font--xl {\n  font-size: $font-size-xl;\n}\n\n.u-font--xxl {\n  font-size: $font-size-xxl;\n}\n\n/**\n * Text Transforms\n */\n.u-text-transform--upper {\n  text-transform: uppercase;\n}\n\n.u-text-transform--lower {\n  text-transform: lowercase;\n}\n\n/**\n * Text Styles\n */\n.u-text-style--italic {\n  font-style: italic;\n}\n\n.u-font-weight--normal {\n  font-weight: normal;\n}\n\n/**\n * Text Positioning\n */\n.u-align--center {\n  text-align: center;\n}\n\n/**\n * Text Decorations\n */\n.u-text-decoration--underline {\n  text-decoration: underline;\n}\n\n/**\n * Rich text editor text\n */\n.o-rte-text {\n  width: 100%;\n  margin: 0 auto;\n\n  & > * + * {\n    margin-top: $space;\n  }\n\n  > dl dd,\n  > dl dt,\n  > ol li,\n  > ul li,\n  > p {\n    @include p;\n  }\n\n  > h1,\n  > h2,\n  > h3,\n  > h4,\n  > h5,\n  > h6 {\n    padding-top: $space;\n    margin-bottom: -$space-half;\n  }\n\n  h2:empty,\n  h3:empty,\n  p:empty {\n    display: none;\n  }\n\n  // Heading + subheading lockup\n  > h2 + h3 {\n    margin-top: 0;\n    padding-top: $space-half;\n  }\n\n  a {\n    text-decoration: underline;\n  }\n\n  hr {\n    margin-top: $space-double;\n    margin-bottom: $space-double;\n  }\n\n  code,\n  pre {\n    font-size: 125%;\n  }\n\n  ol,\n  ul {\n    padding-left: 0;\n    margin-left: 0;\n\n    li {\n      list-style: none;\n      padding-left: 34px;\n      margin-left: 0;\n      position: relative;\n      line-height: 2.1em;\n\n      &::before {\n        color: $c-secondary;\n        width: 10px;\n        display: inline-block;\n        position: absolute;\n        left: 0;\n        font-size: 24px;\n        line-height: 1;\n        top: 4px;\n      }\n\n      li {\n        list-style: none;\n      }\n    }\n  }\n\n  ol {\n    counter-reset: item;\n\n    li {\n      &::before {\n        content: counter(item) \". \";\n        counter-increment: item;\n      }\n\n      li {\n        counter-reset: item;\n\n        &::before {\n          content: '\\002010';\n        }\n      }\n    }\n  }\n\n  ul {\n    li {\n      &::before {\n        content: '\\002022';\n      }\n\n      li {\n        &::before {\n          content: '\\0025E6';\n        }\n      }\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $BUTTONS\n\\* ------------------------------------ */\n\n@mixin o-button {\n  display: inline-flex;\n  position: relative;\n  justify-content: center;\n  align-items: center;\n  transition: $transition-all;\n  text-decoration: none;\n  border-radius: 0;\n  font-size: $body-font-size;\n  font-family: $ff-font--primary;\n  font-weight: bold;\n  text-align: center;\n  line-height: 1;\n  white-space: nowrap;\n  appearance: none;\n  border: none;\n  cursor: pointer;\n  padding: $space $space-double;\n  text-transform: uppercase;\n}\n\n/**\n * Button Primary\n */\n@mixin o-button--primary {\n  background-color: $c-secondary;\n  color: $c-white;\n  filter: brightness(100%);\n\n  &:hover,\n  &:focus {\n    filter: brightness(120%);\n  }\n}\n\n.o-button--primary {\n  @include o-button;\n  @include o-button--primary;\n}\n\n/**\n * Button Secondary\n */\n@mixin o-button--secondary {\n  background-color: $c-secondary;\n  color: $c-white;\n  filter: brightness(100%);\n\n  &:hover,\n  &:focus {\n    filter: brightness(120%);\n  }\n}\n\n.o-button--secondary {\n  @include o-button;\n  @include o-button--secondary;\n}\n\nbutton,\ninput[type=\"submit\"],\n.o-button {\n  @include o-button;\n  @include o-button--primary;\n}\n","/* ------------------------------------ *\\\n    $BLOCKS\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $CARDS\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $HEROS\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $PAGE SECTIONS\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $SPECIFIC FORMS\n\\* ------------------------------------ */\n\n/**\n * Validation\n */\n.has-error {\n  border-color: $c-error !important;\n}\n\n.is-valid {\n  border-color: $c-valid !important;\n}\n\n/**\n * Newsletter Signup\n */\n.o-newsletter-signup {\n  display: flex;\n  flex-direction: column;\n\n  @include media(\">medium\") {\n    flex-direction: row;\n  }\n\n\n  input[type=email] {\n    width: 100%;\n    border: none;\n\n    @include media(\">medium\") {\n      width: calc(100% - 180px);\n    }\n  }\n\n  input[type=submit] {\n    width: 100%;\n    margin-top: $space-half;\n\n    @include media(\">medium\") {\n      margin-top: 0;\n      width: 180px;\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $ICONS\n\\* ------------------------------------ */\n\n/**\n * Icon Sizing\n */\n.o-icon {\n  display: inline-block;\n}\n\n.u-icon--xs {\n  width: $icon-xsmall;\n  height: $icon-xsmall;\n}\n\n.u-icon--s {\n  width: $icon-small;\n  height: $icon-small;\n}\n\n.u-icon--m {\n  width: $icon-medium;\n  height: $icon-medium;\n}\n\n.u-icon--l {\n  width: $icon-large;\n  height: $icon-large;\n}\n\n.u-icon--xl {\n  width: $icon-xlarge;\n  height: $icon-xlarge;\n}\n","/* ------------------------------------ *\\\n    $LIST TYPES\n\\* ------------------------------------ */\n\n/**\n * Numbered List\n */\n.o-list--numbered {\n  counter-reset: item;\n\n  li {\n    display: block;\n\n    &::before {\n      content: counter(item);\n      counter-increment: item;\n      color: $c-white;\n      padding: 10px 15px;\n      border-radius: 3px;\n      background-color: $c-black;\n      font-weight: bold;\n      margin-right: $space;\n      float: left;\n    }\n\n    > * {\n      overflow: hidden;\n    }\n\n    li {\n      counter-reset: item;\n\n      &::before {\n        content: \"\\002010\";\n      }\n    }\n  }\n}\n\n/**\n * Bullet List\n */\n.o-bullet-list {\n  list-style-type: disc;\n  padding-left: $space;\n\n  li {\n    overflow: visible;\n\n    &:last-child {\n      margin-bottom: 0;\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $NAVIGATION\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $MEDIA OBJECTS\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $ARTICLE & RELATED COMPONENTS\n\\* ------------------------------------ */\n\n.page-id-5 {\n  height: 100vh;\n  display: flex;\n  flex-direction: column;\n  justify-content: space-between;\n  background-color: $c-primary;\n\n  .c-main-header {\n    background: url(\"../images/header-bkg.png\") center bottom no-repeat;\n    background-size: cover;\n  }\n}\n\n.l-article--landing {\n  padding: 0 0 $space-double 0;\n  max-width: $small;\n  text-align: center;\n}\n","/* ------------------------------------ *\\\n    $GALLERY\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $FOOTER\n\\* ------------------------------------ */\n\n.c-main-footer {\n  background-color: $c-secondary;\n  color: $c-white;\n\n  &--inner {\n    padding: $space-half 0;\n    text-align: center;\n  }\n}\n","/* ------------------------------------ *\\\n    $HEADER\n\\* ------------------------------------ */\n\n.o-logo {\n  margin: $space-double $space $space-quad $space;\n\n  img {\n    width: 100%;\n    height: auto;\n    max-width: $xsmall;\n    margin: 0 auto;\n    position: relative;\n\n    @include media(\">medium\") {\n      left: -15px;\n    }\n  }\n}\n","/* ------------------------------------ *\\\n    $MAIN CONTENT AREA\n\\* ------------------------------------ */\n","/* ------------------------------------ *\\\n    $ANIMATIONS & TRANSITIONS\n\\* ------------------------------------ */\n\n/**\n * Transitions\n */\n.has-trans {\n  transition: all 0.4s ease-in-out;\n}\n\n.has-trans--fast {\n  transition: all 0.1s ease-in-out;\n}\n\n.has-zoom {\n  overflow: hidden;\n\n  img {\n    transition: transform 0.3s ease-out;\n    transform: scale(1);\n  }\n\n  a:hover {\n    img {\n      transform: scale(1.03);\n    }\n  }\n}\n\n/**\n * Fade Classes\n */\n.has-fadeup {\n  opacity: 0;\n  transform: translate(0, 25px);\n  transition: all 0.6s ease-out 0.5s;\n}\n\n.fadeup,\n.has-fadeup.is-active {\n  opacity: 1;\n  transform: translate(0, 0);\n}\n\n.has-fadein {\n  opacity: 0;\n  transition: all 0.8s ease-out;\n}\n\n.fadein {\n  opacity: 1;\n}\n\n// Fade image in after load.\n.lazyload,\n.lazyloading {\n  opacity: 0;\n  transform: translate(0, 25px);\n  transition: all 0.6s ease-out;\n}\n\n.lazyloaded {\n  opacity: 1;\n  transition: opacity 300ms;\n}\n\n// Bounce up and down.\n@keyframes bounce {\n  0%,\n  100% {\n    transform: translateY(0);\n  }\n\n  20% {\n    transform: translateY(-3px);\n  }\n\n  80% {\n    transform: translateY(3px);\n  }\n}\n","/* ------------------------------------ *\\\n    $COLOR MODIFIERS\n\\* ------------------------------------ */\n\n/**\n * Text Colors\n */\n.u-color--black,\n.u-color--black a {\n  color: $c-black;\n}\n\n.u-color--gray,\n.u-color--gray a {\n  color: $c-gray;\n}\n\n.u-color--gray--light,\n.u-color--gray--light a {\n  color: $c-gray--light;\n}\n\n.u-color--white,\n.u-color--white a {\n  color: $c-white !important;\n}\n\n/**\n * Background Colors\n */\n.u-background-color--none {\n  background: none;\n}\n\n.u-background-color--black {\n  background-color: $c-black;\n}\n\n.u-background-color--gray {\n  background-color: $c-gray;\n}\n\n.u-background-color--gray--light {\n  background-color: $c-gray--light;\n}\n\n.u-background-color--white {\n  background-color: $c-white;\n}\n\n/**\n * SVG Fill Colors\n */\n.u-path-fill--black {\n  path {\n    fill: $c-black;\n  }\n}\n\n.u-path-fill--gray {\n  path {\n    fill: $c-gray;\n  }\n}\n\n.u-path-fill--white {\n  path {\n    fill: $c-white;\n  }\n}\n","/* ------------------------------------ *\\\n    $DISPLAY STATES\n\\* ------------------------------------ */\n\n/**\n * Display Classes\n */\n.u-display--inline-block {\n  display: inline-block;\n}\n\n.u-display--block {\n  display: block;\n}\n\n.u-flex {\n  display: flex;\n}\n\n.u-justify-content--space-between {\n  justify-content: space-between;\n}\n\n.u-justify-content--flex-end {\n  justify-content: flex-end;\n}\n\n.u-align-items--center {\n  align-items: center;\n}\n\n.u-flex-directon--column {\n  flex-direction: column;\n}\n\n// Specific Widths - visible greater than #\n.u-hide-until--s {\n  @include media(\"<=small\") {\n    display: none;\n  }\n}\n\n.u-hide-until--m {\n  @include media(\"<=medium\") {\n    display: none;\n  }\n}\n\n.u-hide-until--l {\n  @include media(\"<=large\") {\n    display: none;\n  }\n}\n\n.u-hide-until--xl {\n  @include media(\"<=xlarge\") {\n    display: none;\n  }\n}\n\n// Specific Widths - hide greater than #\n.u-hide-after--s {\n  @include media(\">small\") {\n    display: none;\n  }\n}\n\n.u-hide-after--m {\n  @include media(\">medium\") {\n    display: none;\n  }\n}\n\n.u-hide-after--l {\n  @include media(\">large\") {\n    display: none;\n  }\n}\n\n.u-hide-after--xl {\n  @include media(\">xlarge\") {\n    display: none;\n  }\n}\n","/* ------------------------------------ *\\\n    $SPACING\n\\* ------------------------------------ */\n\n$sizes: (\"\": $space, --quarter: $space / 4, --half: $space / 2, --and-half: $space * 1.5, --double: $space * 2, --triple: $space * 3, --quad: $space * 4, --zero: 0rem);\n\n$sides: (\"\": \"\", --top: \"-top\", --bottom: \"-bottom\", --left: \"-left\", --right: \"-right\");\n\n@each $size_key, $size_value in $sizes {\n  .u-spacing#{$size_key} {\n    & > * + * {\n      margin-top: #{$size_value};\n    }\n  }\n\n  @each $side_key, $side_value in $sides {\n    .u-padding#{$size_key}#{$side_key} {\n      padding#{$side_value}: #{$size_value};\n    }\n\n    .u-space#{$size_key}#{$side_key} {\n      margin#{$side_value}: #{$size_value};\n    }\n  }\n}\n\n.u-spacing--left {\n  & > * + * {\n    margin-left: $space;\n  }\n}\n","/* ------------------------------------ *\\\n    $HELPER/TRUMP CLASSES\n\\* ------------------------------------ */\n\n/**\n * Completely remove from the flow but leave available to screen readers.\n */\n.is-vishidden,\n.visually-hidden {\n  position: absolute !important;\n  overflow: hidden;\n  width: 1px;\n  height: 1px;\n  padding: 0;\n  border: 0;\n  clip: rect(1px, 1px, 1px, 1px);\n}\n\n/**\n * Hide elements only present and necessary for js enabled browsers.\n */\n.no-js .no-js-hide {\n  display: none;\n}\n\n.u-full-width {\n  width: 100%;\n}\n\n.u-align-center {\n  text-align: center;\n}\n\n.u-reversed-out {\n  color: $c-white;\n\n  p,\n  h1 a,\n  h2 a,\n  h3 a {\n    color: $c-white;\n  }\n}\n\n/**\n * Remove all margins/padding\n */\n.u-no-spacing {\n  padding: 0;\n  margin: 0;\n}\n\n/**\n * Active on/off states\n */\n.u-active--off {\n  display: none;\n}\n\n[class*=\"-is-active\"].js-toggle-parent,\n[class*=\"-is-active\"].js-toggle {\n  .u-active--on {\n    display: none;\n  }\n\n  .u-active--off {\n    display: block;\n  }\n}\n\n[class*=\"-is-active\"] {\n  .u-hide-on-active {\n    display: none;\n  }\n}\n\n/**\n * Breakout content\n */\n.u-breakout {\n  margin-right: -$space;\n  margin-left: -$space;\n\n  @include media(\">xxlarge\") {\n    margin-left: -$space-double;\n    margin-right: -$space-double;\n  }\n}\n\n/**\n * Justify left/right content\n */\n.u-split-content {\n  display: flex;\n\n  @include media(\"<=xsmall\") {\n    flex-wrap: wrap;\n    flex-direction: column-reverse;\n    justify-content: flex-end;\n\n    > * + * {\n      margin-bottom: $space;\n    }\n  }\n\n\n  @include media(\">xsmall\") {\n    justify-content: space-between;\n    align-items: flex-start;\n  }\n}\n"],"sourceRoot":""}]);

// exports


/***/ }),
/* 17 */
/*!****************************************************************************************!*\
  !*** multi ./build/util/../helpers/hmr-client.js ./scripts/main.js ./styles/main.scss ***!
  \****************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__(/*! /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/resources/assets/build/util/../helpers/hmr-client.js */1);
__webpack_require__(/*! ./scripts/main.js */18);
module.exports = __webpack_require__(/*! ./styles/main.scss */22);


/***/ }),
/* 18 */
/*!*************************!*\
  !*** ./scripts/main.js ***!
  \*************************/
/*! no exports provided */
/*! all exports used */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* WEBPACK VAR INJECTION */(function(jQuery) {/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_jquery__ = __webpack_require__(/*! jquery */ 0);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_jquery___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_0_jquery__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1__util_Router__ = __webpack_require__(/*! ./util/Router */ 19);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__routes_common__ = __webpack_require__(/*! ./routes/common */ 21);
// import external dependencies


// import local dependencies



/** Populate Router instance with DOM routes */
var routes = new __WEBPACK_IMPORTED_MODULE_1__util_Router__["a" /* default */]({
  // All pages
  common: __WEBPACK_IMPORTED_MODULE_2__routes_common__["a" /* default */],
});

// Load Events
jQuery(document).ready(function () { return routes.loadEvents(); });

/* WEBPACK VAR INJECTION */}.call(__webpack_exports__, __webpack_require__(/*! jquery */ 0)))

/***/ }),
/* 19 */
/*!********************************!*\
  !*** ./scripts/util/Router.js ***!
  \********************************/
/*! exports provided: default */
/*! exports used: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__camelCase__ = __webpack_require__(/*! ./camelCase */ 20);


/**
 * DOM-based Routing
 *
 * Based on {@link http://goo.gl/EUTi53|Markup-based Unobtrusive Comprehensive DOM-ready Execution} by Paul Irish
 *
 * The routing fires all common scripts, followed by the page specific scripts.
 * Add additional events for more control over timing e.g. a finalize event
 */
var Router = function Router(routes) {
  this.routes = routes;
};

/**
 * Fire Router events
 * @param {string} route DOM-based route derived from body classes (`<body class="...">`)
 * @param {string} [event] Events on the route. By default, `init` and `finalize` events are called.
 * @param {string} [arg] Any custom argument to be passed to the event.
 */
Router.prototype.fire = function fire (route, event, arg) {
    if ( event === void 0 ) event = 'init';

  var fire = route !== '' && this.routes[route] && typeof this.routes[route][event] === 'function';
  if (fire) {
    this.routes[route][event](arg);
  }
};

/**
 * Automatically load and fire Router events
 *
 * Events are fired in the following order:
 ** common init
 ** page-specific init
 ** page-specific finalize
 ** common finalize
 */
Router.prototype.loadEvents = function loadEvents () {
    var this$1 = this;

  // Fire common init JS
  this.fire('common');

  // Fire page-specific init JS, and then finalize JS
  document.body.className
    .toLowerCase()
    .replace(/-/g, '_')
    .split(/\s+/)
    .map(__WEBPACK_IMPORTED_MODULE_0__camelCase__["a" /* default */])
    .forEach(function (className) {
      this$1.fire(className);
      this$1.fire(className, 'finalize');
    });

  // Fire common finalize JS
  this.fire('common', 'finalize');
};

/* harmony default export */ __webpack_exports__["a"] = (Router);


/***/ }),
/* 20 */
/*!***********************************!*\
  !*** ./scripts/util/camelCase.js ***!
  \***********************************/
/*! exports provided: default */
/*! exports used: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/**
 * the most terrible camelizer on the internet, guaranteed!
 * @param {string} str String that isn't camel-case, e.g., CAMeL_CaSEiS-harD
 * @return {string} String converted to camel-case, e.g., camelCaseIsHard
 */
/* harmony default export */ __webpack_exports__["a"] = (function (str) { return ("" + (str.charAt(0).toLowerCase()) + (str.replace(/[\W_]/g, '|').split('|')
  .map(function (part) { return ("" + (part.charAt(0).toUpperCase()) + (part.slice(1))); })
  .join('')
  .slice(1))); });;


/***/ }),
/* 21 */
/*!**********************************!*\
  !*** ./scripts/routes/common.js ***!
  \**********************************/
/*! exports provided: default */
/*! exports used: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
/* WEBPACK VAR INJECTION */(function($) {/* eslint-disable */

/* harmony default export */ __webpack_exports__["a"] = ({
  init: function init() {
    // JavaScript to be fired on all pages

    /**
     * General helper function to support toggle functions.
     */
    var toggleClasses = function(element) {
      var $this = element,
          $togglePrefix = $this.data('prefix') || 'this';

      // If the element you need toggled is relative to the toggle, add the
      // .js-this class to the parent element and "this" to the data-toggled attr.
      if ($this.data('toggled') == "this") {
        var $toggled = $this.closest('.js-this');
      }
      else {
        var $toggled = $('.' + $this.data('toggled'));
      }
      if ($this.attr('aria-expanded', 'true')) {
        $this.attr('aria-expanded', 'true')
      }
      else {
        $this.attr('aria-expanded', 'false')
      }
      $this.toggleClass($togglePrefix + '-is-active');
      $toggled.toggleClass($togglePrefix + '-is-active');

      // Remove a class on another element, if needed.
      if ($this.data('remove')) {
        $('.' + $this.data('remove')).removeClass($this.data('remove'));
      }
    };

    /*
     * Toggle Active Classes
     *
     * @description:
     *  toggle specific classes based on data-attr of clicked element
     *
     * @requires:
     *  'js-toggle' class and a data-attr with the element to be
     *  toggled's class name both applied to the clicked element
     *
     * @example usage:
     *  <span class="js-toggle" data-toggled="toggled-class">Toggler</span>
     *  <div class="toggled-class">This element's class will be toggled</div>
     *
     */
    $('.js-toggle').on('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      toggleClasses($(this));
    });

    // Toggle parent class
    $('.js-toggle-parent').on('click', function(e) {
      e.preventDefault();
      var $this = $(this);
      $this.toggleClass('this-is-active');
      $this.parent().toggleClass('this-is-active');
    });

    // Prevent bubbling to the body. Add this class to the element (or element
    // container) that should allow the click event.
    $('.js-stop-prop').on('click', function(e) {
      e.stopPropagation();
    });

    // Toggle hovered classes
    $('.js-hover').on('mouseenter mouseleave', function(e) {
      e.preventDefault();
      e.stopPropagation();
      toggleClasses($(this));
    });

    $('.js-hover-parent').on('mouseenter mouseleave', function(e) {
      e.preventDefault();
      var $this = $(this);
      $this.toggleClass('this-is-active');
      $this.parent().toggleClass('this-is-active');
    });
  },
  finalize: function finalize() {
    // JavaScript to be fired on all pages, after page specific JS is fired
  },
});

/* WEBPACK VAR INJECTION */}.call(__webpack_exports__, __webpack_require__(/*! jquery */ 0)))

/***/ }),
/* 22 */
/*!**************************!*\
  !*** ./styles/main.scss ***!
  \**************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {


var content = __webpack_require__(/*! !../../../node_modules/cache-loader/dist/cjs.js!../../../node_modules/css-loader??ref--4-3!../../../node_modules/postcss-loader/lib??ref--4-4!../../../node_modules/resolve-url-loader??ref--4-5!../../../node_modules/sass-loader/lib/loader.js??ref--4-6!../../../node_modules/import-glob!./main.scss */ 16);

if(typeof content === 'string') content = [[module.i, content, '']];

var transform;
var insertInto;



var options = {"hmr":true}

options.transform = transform
options.insertInto = undefined;

var update = __webpack_require__(/*! ../../../node_modules/style-loader/lib/addStyles.js */ 30)(content, options);

if(content.locals) module.exports = content.locals;

if(true) {
	module.hot.accept(/*! !../../../node_modules/cache-loader/dist/cjs.js!../../../node_modules/css-loader??ref--4-3!../../../node_modules/postcss-loader/lib??ref--4-4!../../../node_modules/resolve-url-loader??ref--4-5!../../../node_modules/sass-loader/lib/loader.js??ref--4-6!../../../node_modules/import-glob!./main.scss */ 16, function() {
		var newContent = __webpack_require__(/*! !../../../node_modules/cache-loader/dist/cjs.js!../../../node_modules/css-loader??ref--4-3!../../../node_modules/postcss-loader/lib??ref--4-4!../../../node_modules/resolve-url-loader??ref--4-5!../../../node_modules/sass-loader/lib/loader.js??ref--4-6!../../../node_modules/import-glob!./main.scss */ 16);

		if(typeof newContent === 'string') newContent = [[module.i, newContent, '']];

		var locals = (function(a, b) {
			var key, idx = 0;

			for(key in a) {
				if(!b || a[key] !== b[key]) return false;
				idx++;
			}

			for(key in b) idx--;

			return idx === 0;
		}(content.locals, newContent.locals));

		if(!locals) throw new Error('Aborting CSS HMR due to changed css-modules locals.');

		update(newContent);
	});

	module.hot.dispose(function() { update(); });
}

/***/ }),
/* 23 */
/*!*******************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/css-loader/lib/url/escape.js ***!
  \*******************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports) {

module.exports = function escape(url) {
    if (typeof url !== 'string') {
        return url
    }
    // If url is already wrapped in quotes, remove them
    if (/^['"].*['"]$/.test(url)) {
        url = url.slice(1, -1);
    }
    // Should url be wrapped?
    // See https://drafts.csswg.org/css-values-3/#urls
    if (/["'() \t\n]/.test(url)) {
        return '"' + url.replace(/"/g, '\\"').replace(/\n/g, '\\n') + '"'
    }

    return url
}


/***/ }),
/* 24 */
/*!*****************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/css-loader/lib/css-base.js ***!
  \*****************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports) {

/*
	MIT License http://www.opensource.org/licenses/mit-license.php
	Author Tobias Koppers @sokra
*/
// css base code, injected by the css-loader
module.exports = function(useSourceMap) {
	var list = [];

	// return the list of modules as css string
	list.toString = function toString() {
		return this.map(function (item) {
			var content = cssWithMappingToString(item, useSourceMap);
			if(item[2]) {
				return "@media " + item[2] + "{" + content + "}";
			} else {
				return content;
			}
		}).join("");
	};

	// import a list of modules into the list
	list.i = function(modules, mediaQuery) {
		if(typeof modules === "string")
			modules = [[null, modules, ""]];
		var alreadyImportedModules = {};
		for(var i = 0; i < this.length; i++) {
			var id = this[i][0];
			if(typeof id === "number")
				alreadyImportedModules[id] = true;
		}
		for(i = 0; i < modules.length; i++) {
			var item = modules[i];
			// skip already imported module
			// this implementation is not 100% perfect for weird media query combinations
			//  when a module is imported multiple times with different media queries.
			//  I hope this will never occur (Hey this way we have smaller bundles)
			if(typeof item[0] !== "number" || !alreadyImportedModules[item[0]]) {
				if(mediaQuery && !item[2]) {
					item[2] = mediaQuery;
				} else if(mediaQuery) {
					item[2] = "(" + item[2] + ") and (" + mediaQuery + ")";
				}
				list.push(item);
			}
		}
	};
	return list;
};

function cssWithMappingToString(item, useSourceMap) {
	var content = item[1] || '';
	var cssMapping = item[3];
	if (!cssMapping) {
		return content;
	}

	if (useSourceMap && typeof btoa === 'function') {
		var sourceMapping = toComment(cssMapping);
		var sourceURLs = cssMapping.sources.map(function (source) {
			return '/*# sourceURL=' + cssMapping.sourceRoot + source + ' */'
		});

		return [content].concat(sourceURLs).concat([sourceMapping]).join('\n');
	}

	return [content].join('\n');
}

// Adapted from convert-source-map (MIT)
function toComment(sourceMap) {
	// eslint-disable-next-line no-undef
	var base64 = btoa(unescape(encodeURIComponent(JSON.stringify(sourceMap))));
	var data = 'sourceMappingURL=data:application/json;charset=utf-8;base64,' + base64;

	return '/*# ' + data + ' */';
}


/***/ }),
/* 25 */
/*!**************************************!*\
  !*** ./fonts/big_john-webfont.woff2 ***!
  \**************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__.p + "fonts/big_john-webfont.woff2";

/***/ }),
/* 26 */
/*!*************************************!*\
  !*** ./fonts/big_john-webfont.woff ***!
  \*************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__.p + "fonts/big_john-webfont.woff";

/***/ }),
/* 27 */
/*!***************************************************!*\
  !*** ./fonts/gobold_regular_italic-webfont.woff2 ***!
  \***************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__.p + "fonts/gobold_regular_italic-webfont.woff2";

/***/ }),
/* 28 */
/*!**************************************************!*\
  !*** ./fonts/gobold_regular_italic-webfont.woff ***!
  \**************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__.p + "fonts/gobold_regular_italic-webfont.woff";

/***/ }),
/* 29 */
/*!*******************************!*\
  !*** ./images/header-bkg.png ***!
  \*******************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__.p + "images/header-bkg.png";

/***/ }),
/* 30 */
/*!********************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/style-loader/lib/addStyles.js ***!
  \********************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports, __webpack_require__) {

/*
	MIT License http://www.opensource.org/licenses/mit-license.php
	Author Tobias Koppers @sokra
*/

var stylesInDom = {};

var	memoize = function (fn) {
	var memo;

	return function () {
		if (typeof memo === "undefined") memo = fn.apply(this, arguments);
		return memo;
	};
};

var isOldIE = memoize(function () {
	// Test for IE <= 9 as proposed by Browserhacks
	// @see http://browserhacks.com/#hack-e71d8692f65334173fee715c222cb805
	// Tests for existence of standard globals is to allow style-loader
	// to operate correctly into non-standard environments
	// @see https://github.com/webpack-contrib/style-loader/issues/177
	return window && document && document.all && !window.atob;
});

var getTarget = function (target, parent) {
  if (parent){
    return parent.querySelector(target);
  }
  return document.querySelector(target);
};

var getElement = (function (fn) {
	var memo = {};

	return function(target, parent) {
                // If passing function in options, then use it for resolve "head" element.
                // Useful for Shadow Root style i.e
                // {
                //   insertInto: function () { return document.querySelector("#foo").shadowRoot }
                // }
                if (typeof target === 'function') {
                        return target();
                }
                if (typeof memo[target] === "undefined") {
			var styleTarget = getTarget.call(this, target, parent);
			// Special case to return head of iframe instead of iframe itself
			if (window.HTMLIFrameElement && styleTarget instanceof window.HTMLIFrameElement) {
				try {
					// This will throw an exception if access to iframe is blocked
					// due to cross-origin restrictions
					styleTarget = styleTarget.contentDocument.head;
				} catch(e) {
					styleTarget = null;
				}
			}
			memo[target] = styleTarget;
		}
		return memo[target]
	};
})();

var singleton = null;
var	singletonCounter = 0;
var	stylesInsertedAtTop = [];

var	fixUrls = __webpack_require__(/*! ./urls */ 31);

module.exports = function(list, options) {
	if (typeof DEBUG !== "undefined" && DEBUG) {
		if (typeof document !== "object") throw new Error("The style-loader cannot be used in a non-browser environment");
	}

	options = options || {};

	options.attrs = typeof options.attrs === "object" ? options.attrs : {};

	// Force single-tag solution on IE6-9, which has a hard limit on the # of <style>
	// tags it will allow on a page
	if (!options.singleton && typeof options.singleton !== "boolean") options.singleton = isOldIE();

	// By default, add <style> tags to the <head> element
        if (!options.insertInto) options.insertInto = "head";

	// By default, add <style> tags to the bottom of the target
	if (!options.insertAt) options.insertAt = "bottom";

	var styles = listToStyles(list, options);

	addStylesToDom(styles, options);

	return function update (newList) {
		var mayRemove = [];

		for (var i = 0; i < styles.length; i++) {
			var item = styles[i];
			var domStyle = stylesInDom[item.id];

			domStyle.refs--;
			mayRemove.push(domStyle);
		}

		if(newList) {
			var newStyles = listToStyles(newList, options);
			addStylesToDom(newStyles, options);
		}

		for (var i = 0; i < mayRemove.length; i++) {
			var domStyle = mayRemove[i];

			if(domStyle.refs === 0) {
				for (var j = 0; j < domStyle.parts.length; j++) domStyle.parts[j]();

				delete stylesInDom[domStyle.id];
			}
		}
	};
};

function addStylesToDom (styles, options) {
	for (var i = 0; i < styles.length; i++) {
		var item = styles[i];
		var domStyle = stylesInDom[item.id];

		if(domStyle) {
			domStyle.refs++;

			for(var j = 0; j < domStyle.parts.length; j++) {
				domStyle.parts[j](item.parts[j]);
			}

			for(; j < item.parts.length; j++) {
				domStyle.parts.push(addStyle(item.parts[j], options));
			}
		} else {
			var parts = [];

			for(var j = 0; j < item.parts.length; j++) {
				parts.push(addStyle(item.parts[j], options));
			}

			stylesInDom[item.id] = {id: item.id, refs: 1, parts: parts};
		}
	}
}

function listToStyles (list, options) {
	var styles = [];
	var newStyles = {};

	for (var i = 0; i < list.length; i++) {
		var item = list[i];
		var id = options.base ? item[0] + options.base : item[0];
		var css = item[1];
		var media = item[2];
		var sourceMap = item[3];
		var part = {css: css, media: media, sourceMap: sourceMap};

		if(!newStyles[id]) styles.push(newStyles[id] = {id: id, parts: [part]});
		else newStyles[id].parts.push(part);
	}

	return styles;
}

function insertStyleElement (options, style) {
	var target = getElement(options.insertInto)

	if (!target) {
		throw new Error("Couldn't find a style target. This probably means that the value for the 'insertInto' parameter is invalid.");
	}

	var lastStyleElementInsertedAtTop = stylesInsertedAtTop[stylesInsertedAtTop.length - 1];

	if (options.insertAt === "top") {
		if (!lastStyleElementInsertedAtTop) {
			target.insertBefore(style, target.firstChild);
		} else if (lastStyleElementInsertedAtTop.nextSibling) {
			target.insertBefore(style, lastStyleElementInsertedAtTop.nextSibling);
		} else {
			target.appendChild(style);
		}
		stylesInsertedAtTop.push(style);
	} else if (options.insertAt === "bottom") {
		target.appendChild(style);
	} else if (typeof options.insertAt === "object" && options.insertAt.before) {
		var nextSibling = getElement(options.insertAt.before, target);
		target.insertBefore(style, nextSibling);
	} else {
		throw new Error("[Style Loader]\n\n Invalid value for parameter 'insertAt' ('options.insertAt') found.\n Must be 'top', 'bottom', or Object.\n (https://github.com/webpack-contrib/style-loader#insertat)\n");
	}
}

function removeStyleElement (style) {
	if (style.parentNode === null) return false;
	style.parentNode.removeChild(style);

	var idx = stylesInsertedAtTop.indexOf(style);
	if(idx >= 0) {
		stylesInsertedAtTop.splice(idx, 1);
	}
}

function createStyleElement (options) {
	var style = document.createElement("style");

	if(options.attrs.type === undefined) {
		options.attrs.type = "text/css";
	}

	if(options.attrs.nonce === undefined) {
		var nonce = getNonce();
		if (nonce) {
			options.attrs.nonce = nonce;
		}
	}

	addAttrs(style, options.attrs);
	insertStyleElement(options, style);

	return style;
}

function createLinkElement (options) {
	var link = document.createElement("link");

	if(options.attrs.type === undefined) {
		options.attrs.type = "text/css";
	}
	options.attrs.rel = "stylesheet";

	addAttrs(link, options.attrs);
	insertStyleElement(options, link);

	return link;
}

function addAttrs (el, attrs) {
	Object.keys(attrs).forEach(function (key) {
		el.setAttribute(key, attrs[key]);
	});
}

function getNonce() {
	if (false) {
		return null;
	}

	return __webpack_require__.nc;
}

function addStyle (obj, options) {
	var style, update, remove, result;

	// If a transform function was defined, run it on the css
	if (options.transform && obj.css) {
	    result = options.transform(obj.css);

	    if (result) {
	    	// If transform returns a value, use that instead of the original css.
	    	// This allows running runtime transformations on the css.
	    	obj.css = result;
	    } else {
	    	// If the transform function returns a falsy value, don't add this css.
	    	// This allows conditional loading of css
	    	return function() {
	    		// noop
	    	};
	    }
	}

	if (options.singleton) {
		var styleIndex = singletonCounter++;

		style = singleton || (singleton = createStyleElement(options));

		update = applyToSingletonTag.bind(null, style, styleIndex, false);
		remove = applyToSingletonTag.bind(null, style, styleIndex, true);

	} else if (
		obj.sourceMap &&
		typeof URL === "function" &&
		typeof URL.createObjectURL === "function" &&
		typeof URL.revokeObjectURL === "function" &&
		typeof Blob === "function" &&
		typeof btoa === "function"
	) {
		style = createLinkElement(options);
		update = updateLink.bind(null, style, options);
		remove = function () {
			removeStyleElement(style);

			if(style.href) URL.revokeObjectURL(style.href);
		};
	} else {
		style = createStyleElement(options);
		update = applyToTag.bind(null, style);
		remove = function () {
			removeStyleElement(style);
		};
	}

	update(obj);

	return function updateStyle (newObj) {
		if (newObj) {
			if (
				newObj.css === obj.css &&
				newObj.media === obj.media &&
				newObj.sourceMap === obj.sourceMap
			) {
				return;
			}

			update(obj = newObj);
		} else {
			remove();
		}
	};
}

var replaceText = (function () {
	var textStore = [];

	return function (index, replacement) {
		textStore[index] = replacement;

		return textStore.filter(Boolean).join('\n');
	};
})();

function applyToSingletonTag (style, index, remove, obj) {
	var css = remove ? "" : obj.css;

	if (style.styleSheet) {
		style.styleSheet.cssText = replaceText(index, css);
	} else {
		var cssNode = document.createTextNode(css);
		var childNodes = style.childNodes;

		if (childNodes[index]) style.removeChild(childNodes[index]);

		if (childNodes.length) {
			style.insertBefore(cssNode, childNodes[index]);
		} else {
			style.appendChild(cssNode);
		}
	}
}

function applyToTag (style, obj) {
	var css = obj.css;
	var media = obj.media;

	if(media) {
		style.setAttribute("media", media)
	}

	if(style.styleSheet) {
		style.styleSheet.cssText = css;
	} else {
		while(style.firstChild) {
			style.removeChild(style.firstChild);
		}

		style.appendChild(document.createTextNode(css));
	}
}

function updateLink (link, options, obj) {
	var css = obj.css;
	var sourceMap = obj.sourceMap;

	/*
		If convertToAbsoluteUrls isn't defined, but sourcemaps are enabled
		and there is no publicPath defined then lets turn convertToAbsoluteUrls
		on by default.  Otherwise default to the convertToAbsoluteUrls option
		directly
	*/
	var autoFixUrls = options.convertToAbsoluteUrls === undefined && sourceMap;

	if (options.convertToAbsoluteUrls || autoFixUrls) {
		css = fixUrls(css);
	}

	if (sourceMap) {
		// http://stackoverflow.com/a/26603875
		css += "\n/*# sourceMappingURL=data:application/json;base64," + btoa(unescape(encodeURIComponent(JSON.stringify(sourceMap)))) + " */";
	}

	var blob = new Blob([css], { type: "text/css" });

	var oldSrc = link.href;

	link.href = URL.createObjectURL(blob);

	if(oldSrc) URL.revokeObjectURL(oldSrc);
}


/***/ }),
/* 31 */
/*!***************************************************************************************************************!*\
  !*** /Users/kelseycahill/Sites/Angry Duck/wp-content/themes/angry-duck/node_modules/style-loader/lib/urls.js ***!
  \***************************************************************************************************************/
/*! dynamic exports provided */
/*! all exports used */
/***/ (function(module, exports) {


/**
 * When source maps are enabled, `style-loader` uses a link element with a data-uri to
 * embed the css on the page. This breaks all relative urls because now they are relative to a
 * bundle instead of the current page.
 *
 * One solution is to only use full urls, but that may be impossible.
 *
 * Instead, this function "fixes" the relative urls to be absolute according to the current page location.
 *
 * A rudimentary test suite is located at `test/fixUrls.js` and can be run via the `npm test` command.
 *
 */

module.exports = function (css) {
  // get current location
  var location = typeof window !== "undefined" && window.location;

  if (!location) {
    throw new Error("fixUrls requires window.location");
  }

	// blank or null?
	if (!css || typeof css !== "string") {
	  return css;
  }

  var baseUrl = location.protocol + "//" + location.host;
  var currentDir = baseUrl + location.pathname.replace(/\/[^\/]*$/, "/");

	// convert each url(...)
	/*
	This regular expression is just a way to recursively match brackets within
	a string.

	 /url\s*\(  = Match on the word "url" with any whitespace after it and then a parens
	   (  = Start a capturing group
	     (?:  = Start a non-capturing group
	         [^)(]  = Match anything that isn't a parentheses
	         |  = OR
	         \(  = Match a start parentheses
	             (?:  = Start another non-capturing groups
	                 [^)(]+  = Match anything that isn't a parentheses
	                 |  = OR
	                 \(  = Match a start parentheses
	                     [^)(]*  = Match anything that isn't a parentheses
	                 \)  = Match a end parentheses
	             )  = End Group
              *\) = Match anything and then a close parens
          )  = Close non-capturing group
          *  = Match anything
       )  = Close capturing group
	 \)  = Match a close parens

	 /gi  = Get all matches, not the first.  Be case insensitive.
	 */
	var fixedCss = css.replace(/url\s*\(((?:[^)(]|\((?:[^)(]+|\([^)(]*\))*\))*)\)/gi, function(fullMatch, origUrl) {
		// strip quotes (if they exist)
		var unquotedOrigUrl = origUrl
			.trim()
			.replace(/^"(.*)"$/, function(o, $1){ return $1; })
			.replace(/^'(.*)'$/, function(o, $1){ return $1; });

		// already a full url? no change
		if (/^(#|data:|http:\/\/|https:\/\/|file:\/\/\/|\s*$)/i.test(unquotedOrigUrl)) {
		  return fullMatch;
		}

		// convert the url to a full url
		var newUrl;

		if (unquotedOrigUrl.indexOf("//") === 0) {
		  	//TODO: should we add protocol?
			newUrl = unquotedOrigUrl;
		} else if (unquotedOrigUrl.indexOf("/") === 0) {
			// path should be relative to the base url
			newUrl = baseUrl + unquotedOrigUrl; // already starts with '/'
		} else {
			// path should be relative to current directory
			newUrl = currentDir + unquotedOrigUrl.replace(/^\.\//, ""); // Strip leading './'
		}

		// send back the fixed url(...)
		return "url(" + JSON.stringify(newUrl) + ")";
	});

	// send back the fixed css
	return fixedCss;
};


/***/ })
/******/ ]);
//# sourceMappingURL=main.js.map